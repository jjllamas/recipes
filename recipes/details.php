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

<h2 class="text-center"><?= htmlspecialchars($recipe['name']) ?></h2>

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Description</strong>
        <a href="<?= BASE_URL ?>/recipes/edit.php?id=<?= $recipe_id ?>" class="btn btn-warning btn-sm">Edit Recipe</a>
    </div>
    <div class="card-body">
        <p><?= nl2br(htmlspecialchars($recipe['description'])) ?></p>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><strong>Details</strong></div>
    <div class="card-body">
        <p><strong>Categories:</strong>
            <?php foreach ($categories as $category): ?>
                <span class="badge bg-secondary"><?= htmlspecialchars($category) ?></span>
            <?php endforeach; ?>
        </p>
        <p><strong>Preparation Time:</strong> <?= $recipe['prep_time'] ?> minutes</p>
        <p><strong>Cooking Time:</strong> <?= $recipe['cook_time'] ?> minutes</p>
        <p><strong>Difficulty:</strong> <?= htmlspecialchars($recipe['difficulty']) ?></p>
        <p><strong>Portions:</strong> <?= $recipe['portions'] ?></p>
        <p><strong>Calories per Portion:</strong> <?= $recipe['calories_per_portion'] ?> kcal</p>
        <?php if (!empty($recipe['oven_temperature'])): ?>
            <p><strong>Oven Temperature:</strong> <?= $recipe['oven_temperature'] ?> °C</p>
        <?php endif; ?>
        <?php if (!empty($recipe['airfryer_temperature'])): ?>
            <p><strong>Airfryer Temperature:</strong> <?= $recipe['airfryer_temperature'] ?> °C</p>
        <?php endif; ?>
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
