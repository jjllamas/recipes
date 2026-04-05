<?php
require_once __DIR__ . '/../includes/session.php';

$recipe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($recipe_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM recipes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $recipe_id, $_SESSION['user_id']);
    $stmt->execute();
    $recipe_result = $stmt->get_result();

    if ($recipe_result->num_rows > 0) {
        $recipe = $recipe_result->fetch_assoc();
    } else {
        echo "Recipe not found.";
        exit();
    }

    $categories_stmt = $conn->prepare("
        SELECT c.name
        FROM recipe_categories rc
        JOIN categories c ON rc.category_id = c.id
        WHERE rc.recipe_id = ?");
    $categories_stmt->bind_param("i", $recipe_id);
    $categories_stmt->execute();
    $categories = array_column($categories_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'name');

    $ingredients_stmt = $conn->prepare("
        SELECT i.name AS ingredient_name, ri.quantity, ri.unit
        FROM recipe_ingredients ri
        JOIN ingredients i ON ri.ingredient_id = i.id
        WHERE ri.recipe_id = ?");
    $ingredients_stmt->bind_param("i", $recipe_id);
    $ingredients_stmt->execute();
    $ingredients = $ingredients_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    echo "Invalid recipe ID.";
    exit();
}

$pageTitle = htmlspecialchars($recipe['name']);
include __DIR__ . '/../includes/header.php';
?>

<?php
$difficultyColor = ['Easy' => 'success', 'Medium' => 'warning', 'Hard' => 'danger'];
$diffColor = $difficultyColor[$recipe['difficulty']] ?? 'secondary';
?>

<div class="d-flex justify-content-between align-items-center mt-2 mb-1">
    <h2 class="mb-0"><?= htmlspecialchars($recipe['name']) ?></h2>
    <a href="<?= BASE_URL ?>/recipes/edit.php?id=<?= $recipe_id ?>" class="btn btn-warning btn-sm">
        <i class="bi bi-pencil"></i> Edit
    </a>
</div>

<div class="mb-3">
    <?php foreach ($categories as $category): ?>
        <span class="badge bg-secondary"><?= htmlspecialchars($category) ?></span>
    <?php endforeach; ?>
    <span class="badge bg-<?= $diffColor ?> ms-1"><?= htmlspecialchars($recipe['difficulty']) ?></span>
</div>

<!-- Stats row -->
<div class="row g-3 text-center mb-4">
    <div class="col-6 col-md-3">
        <div class="border rounded p-3 h-100">
            <i class="bi bi-stopwatch fs-3 text-primary"></i>
            <div class="fw-bold fs-5"><?= $recipe['prep_time'] ?> min</div>
            <small class="text-muted">Prep Time</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-3 h-100">
            <i class="bi bi-fire fs-3 text-danger"></i>
            <div class="fw-bold fs-5"><?= $recipe['cook_time'] ?> min</div>
            <small class="text-muted">Cook Time</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-3 h-100">
            <i class="bi bi-people fs-3 text-success"></i>
            <div class="fw-bold fs-5"><?= $recipe['portions'] ?></div>
            <small class="text-muted">Portions</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="border rounded p-3 h-100">
            <i class="bi bi-lightning-charge fs-3 text-warning"></i>
            <div class="fw-bold fs-5"><?= $recipe['calories_per_portion'] ?></div>
            <small class="text-muted">kcal / portion</small>
        </div>
    </div>
    <?php if (!empty($recipe['oven_temperature'])): ?>
    <div class="col-6 col-md-3">
        <div class="border rounded p-3 h-100">
            <i class="bi bi-thermometer-half fs-3 text-secondary"></i>
            <div class="fw-bold fs-5"><?= $recipe['oven_temperature'] ?> °C</div>
            <small class="text-muted">Oven</small>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($recipe['airfryer_temperature'])): ?>
    <div class="col-6 col-md-3">
        <div class="border rounded p-3 h-100">
            <i class="bi bi-wind fs-3 text-info"></i>
            <div class="fw-bold fs-5"><?= $recipe['airfryer_temperature'] ?> °C</div>
            <small class="text-muted">Airfryer</small>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="card mt-2">
    <div class="card-header"><strong>Description</strong></div>
    <div class="card-body">
        <p class="mb-0"><?= nl2br(htmlspecialchars($recipe['description'])) ?></p>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><strong>Ingredients</strong></div>
    <div class="card-body">
        <?php if (!empty($ingredients)): ?>
            <ul class="list-group">
                <?php foreach ($ingredients as $ingredient): ?>
                    <li class="list-group-item">
                        <?= htmlspecialchars($ingredient['ingredient_name']) ?>:
                        <?= $ingredient['quantity'] ?>
                        <?= htmlspecialchars($ingredient['unit']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">No ingredients found for this recipe.</p>
        <?php endif; ?>
    </div>
</div>

<p class="mt-3 text-center">
    <button class="btn btn-secondary" onclick="window.history.back();">Back</button>
</p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
