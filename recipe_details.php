<?php
include 'includes/db_connect.php';
include 'web_session_start.php';

// Obtener el ID de la receta desde la URL.
$recipe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($recipe_id > 0) {
    // Consultar la receta desde la base de datos
    $stmt = $conn->prepare("SELECT * FROM recipes WHERE id = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $recipe_result = $stmt->get_result();

    if ($recipe_result->num_rows > 0) {
        $recipe = $recipe_result->fetch_assoc();
    } else {
        echo "Recipe not found.";
        exit();
    }

    // Consultar las categorías asociadas a la receta
    $categories_query = "
        SELECT c.name 
        FROM recipe_categories rc
        JOIN categories c ON rc.category_id = c.id
        WHERE rc.recipe_id = ?";
    $categories_stmt = $conn->prepare($categories_query);
    $categories_stmt->bind_param("i", $recipe_id);
    $categories_stmt->execute();
    $categories_result = $categories_stmt->get_result();

    $categories = [];
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row['name'];
    }

    // Consultar los ingredientes asociados a la receta
    $ingredients_query = "
        SELECT i.name AS ingredient_name, ri.quantity, ri.unit 
        FROM recipe_ingredients ri 
        JOIN ingredients i ON ri.ingredient_id = i.id 
        WHERE ri.recipe_id = ?";
    $ingredients_stmt = $conn->prepare($ingredients_query);
    $ingredients_stmt->bind_param("i", $recipe_id);
    $ingredients_stmt->execute();
    $ingredients_result = $ingredients_stmt->get_result();

    $ingredients = [];
    while ($row = $ingredients_result->fetch_assoc()) {
        $ingredients[] = $row;
    }
} else {
    echo "Invalid recipe ID.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recipe Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2 class="text-center"><?= htmlspecialchars($recipe['name']) ?></h2>

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Description</strong>
            <!-- Botón de edición -->
            <a href="edit_recipe.php?id=<?= $recipe_id ?>" class="btn btn-warning btn-sm">Edit Recipe</a>
        </div>
        <div class="card-body">
            <!-- Mostrar la descripción con formato -->
            <p><?= nl2br(htmlspecialchars($recipe['description'])) ?></p>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <strong>Details</strong>
        </div>
        <div class="card-body">
            <p><strong>Categories:</strong> 
                <?php foreach ($categories as $category): ?>
                    <span class="badge bg-secondary"><?= htmlspecialchars($category) ?></span>
                <?php endforeach; ?>
            </p>
            <p><strong>Preparation Time:</strong> <?= htmlspecialchars($recipe['prep_time']) ?> minutes</p>
            <p><strong>Cooking Time:</strong> <?= htmlspecialchars($recipe['cook_time']) ?> minutes</p>
            <p><strong>Difficulty:</strong> <?= htmlspecialchars($recipe['difficulty']) ?></p>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <strong>Ingredients</strong>
        </div>
        <div class="card-body">
            <!-- Mostrar la lista de ingredientes -->
            <?php if (count($ingredients) > 0): ?>
                <ul class="list-group">
                    <?php foreach ($ingredients as $ingredient): ?>
                        <li class="list-group-item">
                            <?= htmlspecialchars($ingredient['ingredient_name']) ?>: 
                            <?= htmlspecialchars($ingredient['quantity']) ?> 
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

</body>
</html>
