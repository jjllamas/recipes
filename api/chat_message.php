<?php
ini_set('display_errors', '0');
error_reporting(0);
ob_start();
require_once __DIR__ . '/../includes/session.php';
ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

if (empty(ANTHROPIC_API_KEY)) {
    echo json_encode(['error' => 'Anthropic API key not configured.']); exit;
}

$action = $_POST['action'] ?? 'send';

// ── Clear conversation ────────────────────────────────────────────────────────
if ($action === 'clear') {
    unset($_SESSION['chat_history']);
    echo json_encode(['ok' => true]); exit;
}

// ── Apply proposed menu to a week ─────────────────────────────────────────────
if ($action === 'apply') {
    $menu_data = json_decode($_POST['menu_json'] ?? '', true);
    $week      = (int)($_POST['week'] ?? date('W'));
    $year      = (int)($_POST['year'] ?? date('Y'));
    $overwrite = !empty($_POST['overwrite']);

    if (empty($menu_data)) {
        echo json_encode(['error' => 'Invalid menu data']); exit;
    }

    $valid_days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $valid_meals = ['Breakfast','Second Breakfast','Lunch','Afternoon Snack','Dinner'];

    $recipes_stmt = $conn->prepare("SELECT id, name FROM recipes WHERE user_id = ?");
    $recipes_stmt->bind_param("i", $_SESSION['user_id']);
    $recipes_stmt->execute();
    $recipe_map = [];
    foreach ($recipes_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $recipe_map[strtolower($r['name'])] = $r['id'];
    }

    $added = []; $not_found = [];

    $conn->begin_transaction();
    try {
        if ($overwrite) {
            $del = $conn->prepare("DELETE FROM menu_planner WHERE week=? AND year=? AND user_id=?");
            $del->bind_param("iii", $week, $year, $_SESSION['user_id']);
            $del->execute();
        }

        $stmt_chk = $conn->prepare("SELECT id FROM menu_planner WHERE user_id=? AND week=? AND year=? AND day=? AND meal_type=? AND recipe_id=?");
        $stmt_ins = $conn->prepare("INSERT INTO menu_planner (user_id,week,year,day,meal_type,recipe_id) VALUES (?,?,?,?,?,?)");

        foreach ($menu_data as $day => $meals) {
            if (!in_array($day, $valid_days)) continue;
            foreach ($meals as $meal_type => $recipes) {
                if (!in_array($meal_type, $valid_meals)) continue;
                if (!is_array($recipes)) $recipes = [$recipes];
                foreach ($recipes as $name) {
                    $name = trim($name);
                    $key  = strtolower($name);
                    if (!isset($recipe_map[$key])) { $not_found[] = $name; continue; }
                    $rid = $recipe_map[$key];
                    $uid = $_SESSION['user_id'];
                    $stmt_chk->bind_param("iiissi", $uid, $week, $year, $day, $meal_type, $rid);
                    $stmt_chk->execute();
                    if ($stmt_chk->get_result()->num_rows > 0) continue;
                    $stmt_ins->bind_param("iiissi", $uid, $week, $year, $day, $meal_type, $rid);
                    $stmt_ins->execute();
                    $added[] = $name;
                }
            }
        }
        $conn->commit();
        echo json_encode(['ok' => true, 'added' => count($added), 'not_found' => array_unique($not_found), 'week' => $week]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Send message ──────────────────────────────────────────────────────────────
$user_message = trim($_POST['message'] ?? '');
if (empty($user_message)) {
    echo json_encode(['error' => 'Empty message']); exit;
}

// Load user recipes for system prompt
$stmt = $conn->prepare("
    SELECT r.name, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') as cats
    FROM recipes r
    LEFT JOIN recipe_categories rc ON r.id = rc.recipe_id
    LEFT JOIN categories c ON rc.category_id = c.id
    WHERE r.user_id = ?
    GROUP BY r.id
    ORDER BY r.name");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$recipe_list = empty($recipes)
    ? "(no recipes in library yet)"
    : implode("\n", array_map(fn($r) => "- {$r['name']} ({$r['cats']})", $recipes));

$current_week = (int)date('W');
$current_year = (int)date('Y');

$system_prompt = <<<PROMPT
You are a friendly meal planning assistant integrated into a recipe planner app.

The user's recipe library is listed below. Each line is one recipe name — these are the ONLY valid values you may place in any menu slot:
{$recipe_list}

Your goal: propose a complete weekly menu using ONLY recipes from that list.

STRICT rules — follow every one without exception:
1. COPY recipe names CHARACTER BY CHARACTER from the list above. Never paraphrase, translate, shorten, or reword them. If the list says "Pechuga de pollo horneada a las finas hierbas", use exactly that string.
2. NEVER put an ingredient (e.g. "chicken", "salmon", "rice") in a menu slot. Every slot must contain a recipe name from the list, or be left empty.
3. NEVER invent or suggest a recipe that is not in the list.
4. Avoid repeating the same recipe more than once within the same week. If the library is small and repetition is unavoidable, minimise it and spread repeated recipes as far apart as possible.
5. Respond in the same language the user writes in.
6. When you have enough information, call the propose_menu tool immediately without asking further questions unless something critical is missing.
- The meal slots are: Breakfast, Second Breakfast, Lunch, Afternoon Snack, Dinner.
- Current week: {$current_week}, year: {$current_year}.
PROMPT;

// Build conversation history
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}
$_SESSION['chat_history'][] = ['role' => 'user', 'content' => $user_message];

// Tool definition
$tool = [
    'name'        => 'propose_menu',
    'description' => 'Propose a complete weekly menu using only recipes from the user\'s library.',
    'input_schema' => [
        'type'       => 'object',
        'required'   => ['week', 'year', 'summary', 'menu'],
        'properties' => [
            'week'    => ['type' => 'integer'],
            'year'    => ['type' => 'integer'],
            'summary' => ['type' => 'string', 'description' => 'Brief explanation of the menu choices in the user\'s language'],
            'menu'    => [
                'type'        => 'object',
                'description' => 'Keys are day names, values are objects with meal type keys and arrays of recipe names',
            ],
        ],
    ],
];

$payload = json_encode([
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 4096,
    'system'     => $system_prompt,
    'tools'      => [$tool],
    'messages'   => $_SESSION['chat_history'],
]);

set_time_limit(60);
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 529 || $http_code === 503) {
    echo json_encode(['error' => 'API temporarily overloaded. Please try again.']); exit;
}
if ($http_code !== 200) {
    $e = json_decode($response, true);
    echo json_encode(['error' => $e['error']['message'] ?? 'API error ' . $http_code]); exit;
}

$data        = json_decode($response, true);
$stop_reason = $data['stop_reason'] ?? '';
$content     = $data['content'] ?? [];

$text_reply   = null;
$menu_proposal = null;

foreach ($content as $block) {
    if ($block['type'] === 'text') {
        $text_reply = $block['text'];
    }
    if ($block['type'] === 'tool_use' && $block['name'] === 'propose_menu') {
        $menu_proposal = $block['input'];
        $tool_use_id   = $block['id'];
    }
}

// Store assistant message in history
$_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $content];

// If tool was used, add tool_result so conversation can continue
if ($menu_proposal && isset($tool_use_id)) {
    $_SESSION['chat_history'][] = [
        'role'    => 'user',
        'content' => [[
            'type'        => 'tool_result',
            'tool_use_id' => $tool_use_id,
            'content'     => 'Menu proposal shown to user.',
        ]],
    ];
}

echo json_encode([
    'reply'         => $text_reply,
    'menu_proposal' => $menu_proposal,
]);
