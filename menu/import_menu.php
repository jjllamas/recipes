<?php
require_once __DIR__ . '/../includes/session.php';

$current_week = (int)date('W');
$current_year = (int)date('Y');

$results = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_json  = trim($_POST['json_data'] ?? '');
    $overwrite = !empty($_POST['overwrite']);

    if (empty($raw_json)) {
        $error = 'Please paste the JSON before submitting.';
    } else {
        $data = json_decode($raw_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = 'Invalid JSON: ' . json_last_error_msg() . '. Make sure you copied the full JSON block.';
        } elseif (empty($data['menu']) || !is_array($data['menu'])) {
            $error = 'The JSON must have a <code>menu</code> object at the top level.';
        } else {
            $week = isset($data['week']) ? (int)$data['week'] : $current_week;
            $year = isset($data['year']) ? (int)$data['year'] : $current_year;

            $valid_days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            $valid_meals = ['Breakfast','Second Breakfast','Lunch','Afternoon Snack','Dinner'];

            // Build recipe name → id map (case-insensitive)
            $recipes_stmt = $conn->prepare("SELECT id, name FROM recipes WHERE user_id = ?");
            $recipes_stmt->bind_param("i", $_SESSION['user_id']);
            $recipes_stmt->execute();
            $recipe_map = [];
            foreach ($recipes_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $recipe_map[strtolower($r['name'])] = $r['id'];
            }

            $results = ['added' => [], 'not_found' => [], 'skipped' => []];

            $conn->begin_transaction();
            try {
                if ($overwrite) {
                    $del = $conn->prepare("DELETE FROM menu_planner WHERE week = ? AND year = ? AND user_id = ?");
                    $del->bind_param("iii", $week, $year, $_SESSION['user_id']);
                    $del->execute();
                }

                $stmt_check = $conn->prepare("SELECT id FROM menu_planner WHERE user_id=? AND week=? AND year=? AND day=? AND meal_type=? AND recipe_id=?");
                $stmt_ins   = $conn->prepare("INSERT INTO menu_planner (user_id, week, year, day, meal_type, recipe_id) VALUES (?,?,?,?,?,?)");

                foreach ($data['menu'] as $day => $meals) {
                    if (!in_array($day, $valid_days)) continue;

                    foreach ($meals as $meal_type => $recipe_names) {
                        if (!in_array($meal_type, $valid_meals)) continue;
                        if (!is_array($recipe_names)) $recipe_names = [$recipe_names];

                        foreach ($recipe_names as $recipe_name) {
                            $recipe_name = trim($recipe_name);
                            $key = strtolower($recipe_name);

                            if (!isset($recipe_map[$key])) {
                                $results['not_found'][] = $recipe_name;
                                continue;
                            }

                            $recipe_id = $recipe_map[$key];
                            $uid = $_SESSION['user_id'];

                            // Skip if already exists (unless overwrite cleared the week)
                            $stmt_check->bind_param("iiissi", $uid, $week, $year, $day, $meal_type, $recipe_id);
                            $stmt_check->execute();
                            if ($stmt_check->get_result()->num_rows > 0) {
                                $results['skipped'][] = "$recipe_name ($day / $meal_type)";
                                continue;
                            }

                            $stmt_ins->bind_param("iiissi", $uid, $week, $year, $day, $meal_type, $recipe_id);
                            $stmt_ins->execute();
                            $results['added'][] = "$recipe_name ($day / $meal_type)";
                        }
                    }
                }

                $conn->commit();
                $results['week'] = $week;
                $results['year'] = $year;

            } catch (Exception $e) {
                $conn->rollback();
                $error   = 'Database error: ' . htmlspecialchars($e->getMessage());
                $results = null;
            }
        }
    }
}

$pageTitle = 'Import Weekly Menu';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-9">

<h2 class="text-center mb-1">🗓️ Import Weekly Menu from JSON</h2>
<p class="text-center text-muted mb-4">Generate the menu with Claude.ai and paste the JSON here.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if ($results): ?>
    <?php if (!empty($results['added'])): ?>
        <div class="alert alert-success">
            <strong>✅ <?= count($results['added']) ?> entries added to week <?= $results['week'] ?> / <?= $results['year'] ?>:</strong>
            <ul class="mb-0 mt-2" style="columns:2;">
                <?php foreach ($results['added'] as $entry): ?>
                    <li><?= htmlspecialchars($entry) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($results['not_found'])): ?>
        <div class="alert alert-warning">
            <strong>⚠️ <?= count($results['not_found']) ?> recipes not found in your library (skipped):</strong>
            <ul class="mb-0 mt-2">
                <?php foreach (array_unique($results['not_found']) as $name): ?>
                    <li><?= htmlspecialchars($name) ?> — <a href="<?= BASE_URL ?>/recipes/create.php">create it</a> or <a href="<?= BASE_URL ?>/recipes/import_pdf.php">import from JSON</a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($results['skipped'])): ?>
        <div class="alert alert-secondary">
            <strong>⏭️ <?= count($results['skipped']) ?> already existed (skipped):</strong>
        </div>
    <?php endif; ?>
    <div class="text-center mt-3">
        <a href="<?= BASE_URL ?>/menu/?week=<?= $results['week'] ?>" class="btn btn-primary">View Week <?= $results['week'] ?></a>
        <a href="<?= BASE_URL ?>/menu/import_menu.php" class="btn btn-outline-secondary ms-2">Import another week</a>
    </div>
<?php else: ?>

    <!-- Step 1 -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            <span class="badge bg-primary me-2">1</span> Generate the menu with Claude.ai
        </div>
        <div class="card-body">
            <p class="mb-2">Open <a href="https://claude.ai" target="_blank">claude.ai</a>, sube tu PDF si quieres basarte en él, y envía este prompt:</p>
            <div class="bg-light border rounded p-3 font-monospace small" id="promptText" style="white-space:pre-wrap;">Crea un menú semanal completo (semana <?= $current_week ?> del <?= $current_year ?>) basándote en las recetas del PDF adjunto (o en recetas cetogénicas/saludables si no hay PDF).

Devuelve SOLO un bloque JSON con esta estructura exacta, sin texto adicional:

{
  "week": <?= $current_week ?>,
  "year": <?= $current_year ?>,
  "menu": {
    "Monday":    { "Breakfast": ["Nombre receta"], "Second Breakfast": ["Nombre receta"], "Lunch": ["Nombre receta"], "Afternoon Snack": ["Nombre receta"], "Dinner": ["Nombre receta"] },
    "Tuesday":   { "Breakfast": ["Nombre receta"], "Second Breakfast": ["Nombre receta"], "Lunch": ["Nombre receta"], "Afternoon Snack": ["Nombre receta"], "Dinner": ["Nombre receta"] },
    "Wednesday": { "Breakfast": ["Nombre receta"], "Second Breakfast": ["Nombre receta"], "Lunch": ["Nombre receta"], "Afternoon Snack": ["Nombre receta"], "Dinner": ["Nombre receta"] },
    "Thursday":  { "Breakfast": ["Nombre receta"], "Second Breakfast": ["Nombre receta"], "Lunch": ["Nombre receta"], "Afternoon Snack": ["Nombre receta"], "Dinner": ["Nombre receta"] },
    "Friday":    { "Breakfast": ["Nombre receta"], "Second Breakfast": ["Nombre receta"], "Lunch": ["Nombre receta"], "Afternoon Snack": ["Nombre receta"], "Dinner": ["Nombre receta"] },
    "Saturday":  { "Breakfast": ["Nombre receta"], "Second Breakfast": ["Nombre receta"], "Lunch": ["Nombre receta"], "Afternoon Snack": ["Nombre receta"], "Dinner": ["Nombre receta"] },
    "Sunday":    { "Breakfast": ["Nombre receta"], "Second Breakfast": ["Nombre receta"], "Lunch": ["Nombre receta"], "Afternoon Snack": ["Nombre receta"], "Dinner": ["Nombre receta"] }
  }
}

Usa los nombres EXACTOS de las recetas del PDF. Cada franja puede tener una o más recetas en el array.</div>
            <button class="btn btn-outline-secondary btn-sm mt-2"
                    onclick="navigator.clipboard.writeText(document.getElementById('promptText').textContent.trim()); this.textContent='✅ Copied!'">
                📋 Copy prompt
            </button>
        </div>
    </div>

    <!-- Step 2 -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            <span class="badge bg-primary me-2">2</span> Paste the JSON here
        </div>
        <div class="card-body">
            <form method="POST">
                <textarea name="json_data" class="form-control font-monospace"
                          rows="14" placeholder='{ "week": <?= $current_week ?>, "year": <?= $current_year ?>, "menu": { ... } }'
                          required><?= htmlspecialchars($_POST['json_data'] ?? '') ?></textarea>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="overwrite" id="overwrite" value="1">
                    <label class="form-check-label" for="overwrite">
                        Replace existing entries for that week (if unchecked, existing entries are kept)
                    </label>
                </div>

                <button type="submit" class="btn btn-success w-100 mt-3">
                    <i class="bi bi-calendar-check"></i> Import Menu
                </button>
            </form>
        </div>
    </div>

<?php endif; ?>

<div class="text-center">
    <a href="<?= BASE_URL ?>/menu/" class="btn btn-outline-secondary btn-sm">← Back to Menu Planner</a>
</div>

</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
