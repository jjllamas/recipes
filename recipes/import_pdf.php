<?php
require_once __DIR__ . '/../includes/session.php';

$results = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_json = trim($_POST['json_data'] ?? '');

    if (empty($raw_json)) {
        $error = 'Please paste the JSON before submitting.';
    } else {
        $data = json_decode($raw_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = 'Invalid JSON: ' . json_last_error_msg() . '. Make sure you copied the full JSON block.';
        } elseif (empty($data['recipes']) || !is_array($data['recipes'])) {
            $error = 'The JSON must have a <code>recipes</code> array at the top level.';
        } else {
            $results = ['created' => [], 'skipped' => [], 'errors' => []];

            $cats    = $conn->query("SELECT id, name FROM categories")->fetch_all(MYSQLI_ASSOC);
            $cat_map = []; // keyed by lowercase name
            foreach ($cats as $c) {
                $cat_map[strtolower($c['name'])] = ['id' => $c['id'], 'name' => $c['name']];
            }

            $conn->begin_transaction();
            try {
                $stmt_dup = $conn->prepare("SELECT id FROM recipes WHERE LOWER(name) = LOWER(?) AND user_id = ?");
                $stmt_rec = $conn->prepare("INSERT INTO recipes (user_id, name, description, category_id, prep_time, cook_time, difficulty, portions, calories_per_portion, oven_temperature, airfryer_temperature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_rc  = $conn->prepare("INSERT IGNORE INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)");
                $stmt_ing = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES (?, ?, ?, ?)");

                foreach ($data['recipes'] as $r) {
                    if (empty($r['name'])) continue;

                    $stmt_dup->bind_param("si", $r['name'], $_SESSION['user_id']);
                    $stmt_dup->execute();
                    if ($stmt_dup->get_result()->num_rows > 0) {
                        $results['skipped'][] = $r['name'];
                        continue;
                    }

                    // Resolve/create categories
                    foreach ($r['categories'] ?? [] as $cat_name) {
                        $key = strtolower($cat_name);
                        if (!isset($cat_map[$key])) {
                            $ins = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                            $ins->bind_param("s", $cat_name);
                            $ins->execute();
                            $cat_map[$key] = ['id' => $ins->insert_id, 'name' => $cat_name];
                        }
                    }
                    $primary_cat_id = !empty($r['categories'])
                        ? $cat_map[strtolower($r['categories'][0])]['id']
                        : 1;

                    $name     = $r['name'];
                    $desc     = $r['description'] ?? '';
                    $prep     = (int)($r['prep_time'] ?? 0);
                    $cook     = (int)($r['cook_time'] ?? 0);
                    $diff     = $r['difficulty'] ?? 'Medium';
                    $portions = (int)($r['portions'] ?? 4);
                    $kcal     = (int)($r['calories_per_portion'] ?? 0);
                    $oven     = !empty($r['oven_temperature'])     ? (int)$r['oven_temperature']     : null;
                    $airfryer = !empty($r['airfryer_temperature']) ? (int)$r['airfryer_temperature'] : null;
                    $uid      = $_SESSION['user_id'];

                    $stmt_rec->bind_param("issiiisiiii", $uid, $name, $desc, $primary_cat_id, $prep, $cook, $diff, $portions, $kcal, $oven, $airfryer);
                    $stmt_rec->execute();
                    $recipe_id = $stmt_rec->insert_id;

                    foreach ($r['categories'] ?? [] as $cat_name) {
                        $cid = $cat_map[strtolower($cat_name)]['id'];
                        $stmt_rc->bind_param("ii", $recipe_id, $cid);
                        $stmt_rc->execute();
                    }

                    foreach ($r['ingredients'] ?? [] as $ing) {
                        $ing_name = $ing['name'] ?? '';
                        $qty      = (float)($ing['quantity'] ?? 0);
                        $unit     = $ing['unit'] ?? '';
                        if (empty($ing_name)) continue;

                        $chk = $conn->prepare("SELECT id FROM ingredients WHERE LOWER(name) = LOWER(?)");
                        $chk->bind_param("s", $ing_name);
                        $chk->execute();
                        $row = $chk->get_result()->fetch_assoc();
                        if ($row) {
                            $ing_id = $row['id'];
                        } else {
                            $ins_ing = $conn->prepare("INSERT INTO ingredients (name) VALUES (?)");
                            $ins_ing->bind_param("s", $ing_name);
                            $ins_ing->execute();
                            $ing_id = $ins_ing->insert_id;
                        }

                        $stmt_ing->bind_param("iids", $recipe_id, $ing_id, $qty, $unit);
                        $stmt_ing->execute();
                    }

                    $results['created'][] = $r['name'];
                }

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $error   = 'Database error: ' . htmlspecialchars($e->getMessage());
                $results = null;
            }
        }
    }
}

$pageTitle = 'Import Recipes from JSON';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-9">

<h2 class="text-center mb-1">📋 Import Recipes from JSON</h2>
<p class="text-center text-muted mb-4">Generate the JSON with Claude.ai and paste it here to import your recipes.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if ($results): ?>
    <?php if (!empty($results['created'])): ?>
        <div class="alert alert-success">
            <strong>✅ <?= count($results['created']) ?> recipe<?= count($results['created']) > 1 ? 's' : '' ?> created:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($results['created'] as $name): ?>
                    <li><?= htmlspecialchars($name) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($results['skipped'])): ?>
        <div class="alert alert-warning">
            <strong>⏭️ <?= count($results['skipped']) ?> already existed (skipped):</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($results['skipped'] as $name): ?>
                    <li><?= htmlspecialchars($name) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <div class="text-center mt-3">
        <a href="<?= BASE_URL ?>/recipes/" class="btn btn-primary">Go to Recipe Library</a>
        <a href="<?= BASE_URL ?>/recipes/import_pdf.php" class="btn btn-outline-secondary ms-2">Import more</a>
    </div>
<?php else: ?>

    <!-- Step 1 -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            <span class="badge bg-primary me-2">1</span> Generate the JSON with Claude.ai
        </div>
        <div class="card-body">
            <p class="mb-2">Open <a href="https://claude.ai" target="_blank">claude.ai</a>, upload your PDF and send this prompt:</p>
            <div class="bg-light border rounded p-3 font-monospace small" style="white-space:pre-wrap;">Tengo un libro/PDF de recetas. Extrae TODAS las recetas que encuentres y devuélvelas como un único bloque JSON con esta estructura exacta, sin texto adicional antes ni después:

{
  "recipes": [
    {
      "name": "Nombre de la receta",
      "description": "Descripción y pasos de preparación",
      "categories": ["Main dish"],
      "prep_time": 15,
      "cook_time": 30,
      "difficulty": "Easy",
      "portions": 4,
      "calories_per_portion": 350,
      "oven_temperature": null,
      "airfryer_temperature": null,
      "ingredients": [
        { "name": "Pollo", "quantity": 500, "unit": "g" },
        { "name": "Aceite de oliva", "quantity": 2, "unit": "tbsp" }
      ]
    }
  ]
}

Categorías disponibles: Breakfast, Snack, Main dish, Side Dish, Dessert.
Difficulty solo puede ser: Easy, Medium o Hard.
Si un valor no aparece en el PDF, usa una estimación razonable.
oven_temperature y airfryer_temperature van en °C o null si no aplica.</div>
            <button class="btn btn-outline-secondary btn-sm mt-2"
                    onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent); this.textContent='✅ Copied!'">
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
                          rows="12" placeholder='{ "recipes": [ ... ] }'
                          required><?= htmlspecialchars($_POST['json_data'] ?? '') ?></textarea>
                <button type="submit" class="btn btn-success w-100 mt-3">
                    <i class="bi bi-cloud-upload"></i> Import Recipes
                </button>
            </form>
        </div>
    </div>

<?php endif; ?>

<div class="text-center">
    <a href="<?= BASE_URL ?>/recipes/" class="btn btn-outline-secondary btn-sm">← Back to Recipe Library</a>
</div>

</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
