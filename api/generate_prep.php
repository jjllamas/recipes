<?php
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}
if (empty(ANTHROPIC_API_KEY)) {
    echo json_encode(['error' => 'Anthropic API key not configured.']); exit;
}

$recipe_id = (int)($_POST['recipe_id'] ?? 0);
if (!$recipe_id) {
    echo json_encode(['error' => 'Invalid recipe ID']); exit;
}

$stmt = $conn->prepare("SELECT r.name, r.description FROM recipes r WHERE r.id = ? AND r.user_id = ?");
$stmt->bind_param("ii", $recipe_id, $_SESSION['user_id']);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();
if (!$recipe) { echo json_encode(['error' => 'Recipe not found']); exit; }

$ing_stmt = $conn->prepare("SELECT i.name, ri.quantity, ri.unit FROM recipe_ingredients ri JOIN ingredients i ON ri.ingredient_id = i.id WHERE ri.recipe_id = ?");
$ing_stmt->bind_param("i", $recipe_id);
$ing_stmt->execute();
$ingredients = $ing_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ing_list = implode(', ', array_map(fn($i) => "{$i['quantity']} {$i['unit']} {$i['name']}", $ingredients));

$prompt = "Recipe: {$recipe['name']}\nIngredients: {$ing_list}\nDescription: {$recipe['description']}\n\nBased on this recipe, write a short, practical note (1-3 bullet points, max 60 words) describing what should be prepared or done THE DAY BEFORE to make cooking easier or faster. Think about: marinating, soaking, defrosting, pre-cutting, pre-cooking, making sauces, etc. If nothing needs to be done the day before, reply with exactly: null\n\nRespond in the same language as the recipe name. Only output the prep notes or 'null', nothing else.";

$payload = json_encode([
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 200,
    'messages'   => [['role' => 'user', 'content' => $prompt]],
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    $e = json_decode($response, true);
    echo json_encode(['error' => $e['error']['message'] ?? 'API error ' . $http_code]); exit;
}

$data = json_decode($response, true);
$text = trim($data['content'][0]['text'] ?? '');

if (strtolower($text) === 'null' || empty($text)) {
    echo json_encode(['prep_ahead' => null]); exit;
}

// Save to DB
$upd = $conn->prepare("UPDATE recipes SET prep_ahead = ? WHERE id = ? AND user_id = ?");
$upd->bind_param("sii", $text, $recipe_id, $_SESSION['user_id']);
$upd->execute();

echo json_encode(['prep_ahead' => $text]);
