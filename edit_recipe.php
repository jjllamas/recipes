<?php
include 'includes/db_connect.php';
include 'web_session_start.php';

// Obtener la receta a editar
if (!isset($_GET['id'])) {
    die("Recipe ID is required.");
}

$recipe_id = (int)$_GET['id'];

// Obtener detalles de la receta
$query = "SELECT * FROM recipes WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $recipe_id, $_SESSION['user_id']);
$stmt->execute();
$recipe_result = $stmt->get_result();
$recipe = $recipe_result->fetch_assoc();

if (!$recipe) {
    die("Recipe not found.");
}

// Obtener ingredientes de la receta
$ingredients_query = "SELECT ri.id as recipe_ingredient_id, i.name, ri.quantity, ri.unit
                      FROM recipe_ingredients ri
                      JOIN ingredients i ON ri.ingredient_id = i.id
                      WHERE ri.recipe_id = ?";
$ingredients_stmt = $conn->prepare($ingredients_query);
$ingredients_stmt->bind_param("i", $recipe_id);
$ingredients_stmt->execute();
$ingredients_result = $ingredients_stmt->get_result();

// Obtener categorías existentes para la receta
$recipe_categories_query = "SELECT category_id FROM recipe_categories WHERE recipe_id = ?";
$recipe_categories_stmt = $conn->prepare($recipe_categories_query);
$recipe_categories_stmt->bind_param("i", $recipe_id);
$recipe_categories_stmt->execute();
$recipe_categories_result = $recipe_categories_stmt->get_result();
$selected_categories = [];
while ($row = $recipe_categories_result->fetch_assoc()) {
    $selected_categories[] = $row['category_id'];
}

// Obtener todas las categorías para el formulario
$categories_result = $conn->query("SELECT id, name FROM categories");

// Obtener todos los ingredientes para autocompletado
$all_ingredients_query = "SELECT id, name FROM ingredients ORDER BY name ASC";
$all_ingredients_result = $conn->query($all_ingredients_query);
$all_ingredients = [];
while ($row = $all_ingredients_result->fetch_assoc()) {
    $all_ingredients[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = htmlspecialchars($_POST['name']);
    $description = htmlspecialchars($_POST['description']);
    $categories = $_POST['categories']; // Array de categorías seleccionadas
    $prep_time = (int)$_POST['prep_time'];
    $cook_time = (int)$_POST['cook_time'];
    $difficulty = $_POST['difficulty'];
    $portions = (int)$_POST['portions'];
    $calories_per_portion = (int)$_POST['calories_per_portion'];
    $oven_temperature = !empty($_POST['oven_temperature']) ? (int)$_POST['oven_temperature'] : NULL;
    $airfryer_temperature = !empty($_POST['airfryer_temperature']) ? (int)$_POST['airfryer_temperature'] : NULL;

    $conn->begin_transaction();
    try {
        // Actualizar receta
        $update_stmt = $conn->prepare("UPDATE recipes SET name = ?, description = ?, prep_time = ?, cook_time = ?, difficulty = ?, portions = ?, calories_per_portion = ?, oven_temperature = ?, airfryer_temperature = ? WHERE id = ? AND user_id = ?");
        $update_stmt->bind_param("sssiisiiiii", $name, $description, $prep_time, $cook_time, $difficulty, $portions, $calories_per_portion, $oven_temperature, $airfryer_temperature, $recipe_id, $_SESSION['user_id']);
        $update_stmt->execute();

        // Eliminar todas las categorías actuales de la receta
        $delete_categories_stmt = $conn->prepare("DELETE FROM recipe_categories WHERE recipe_id = ?");
        $delete_categories_stmt->bind_param("i", $recipe_id);
        $delete_categories_stmt->execute();

        // Insertar nuevas categorías seleccionadas
        $insert_category_stmt = $conn->prepare("INSERT INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)");
        foreach ($categories as $category_id) {
            $insert_category_stmt->bind_param("ii", $recipe_id, $category_id);
            $insert_category_stmt->execute();
        }

        // Eliminar todos los ingredientes actuales de la receta
        $delete_ingredients_stmt = $conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?");
        $delete_ingredients_stmt->bind_param("i", $recipe_id);
        $delete_ingredients_stmt->execute();

        // Insertar ingredientes del formulario
        $ingredients = $_POST['ingredients'];
        $insert_stmt = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES (?, ?, ?, ?)");
        foreach ($ingredients as $ingredient) {
            if (!empty($ingredient['name']) && $ingredient['quantity'] > 0 && !empty($ingredient['unit'])) {
                $ingredient_name = htmlspecialchars($ingredient['name']);
                $quantity = (float)$ingredient['quantity'];
                $unit = htmlspecialchars($ingredient['unit']);

                // Buscar o insertar el ingrediente en la tabla de ingredientes
                $ingredient_stmt = $conn->prepare("SELECT id FROM ingredients WHERE name = ?");
                $ingredient_stmt->bind_param("s", $ingredient_name);
                $ingredient_stmt->execute();
                $ingredient_stmt->store_result();

                if ($ingredient_stmt->num_rows > 0) {
                    // Obtener el ID del ingrediente existente
                    $ingredient_stmt->bind_result($ingredient_id);
                    $ingredient_stmt->fetch();
                } else {
                    // Insertar el nuevo ingrediente
                    $ingredient_stmt = $conn->prepare("INSERT INTO ingredients (name) VALUES (?)");
                    $ingredient_stmt->bind_param("s", $ingredient_name);
                    $ingredient_stmt->execute();
                    $ingredient_id = $ingredient_stmt->insert_id;
                }

                // Insertar el ingrediente en la receta
                $insert_stmt->bind_param("iids", $recipe_id, $ingredient_id, $quantity, $unit);
                $insert_stmt->execute();
            }
        }

        $conn->commit();
        header("Location: recipe_details.php?id=$recipe_id");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: Could not update recipe. " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Recipe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        // Función para agregar un nuevo campo de ingrediente
        function addIngredient() {
            const ingredientIndex = document.querySelectorAll('.ingredient-row').length;
            const ingredientTemplate = `
                <div class="ingredient-row row g-3 align-items-end mb-3">
                    <div class="col-md-4">
                        <label for="ingredients[${ingredientIndex}][name]" class="form-label">Ingredient Name:</label>
                        <input list="ingredients-list" name="ingredients[${ingredientIndex}][name]" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="ingredients[${ingredientIndex}][quantity]" class="form-label">Quantity:</label>
                        <input type="number" step="any" name="ingredients[${ingredientIndex}][quantity]" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="ingredients[${ingredientIndex}][unit]" class="form-label">Unit:</label>
                        <input type="text" name="ingredients[${ingredientIndex}][unit]" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger" onclick="removeIngredient(this)">Remove</button>
                    </div>
                </div>`;
            document.getElementById('ingredients-container').insertAdjacentHTML('beforeend', ingredientTemplate);
        }

        // Función para eliminar un campo de ingrediente
        function removeIngredient(button) {
            button.closest('.ingredient-row').remove();
        }
    </script>
</head>
<body class="container mt-5">
    <h2 class="text-center">✏️ Edit Recipe</h2>
    <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form action="edit_recipe.php?id=<?= $recipe_id ?>" method="POST" class="mt-4">
        <div class="mb-3">
            <label for="name" class="form-label">Recipe Name:</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($recipe['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description:</label>
            <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($recipe['description']) ?></textarea>
        </div>
        <div class="mb-3">
            <label for="categories" class="form-label">Categories:</label>
            <select name="categories[]" class="form-select" multiple required>
                <?php while ($category = $categories_result->fetch_assoc()): ?>
                    <option value="<?= $category['id'] ?>" <?= in_array($category['id'], $selected_categories) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="prep_time" class="form-label">Preparation Time (minutes):</label>
            <input type="number" name="prep_time" class="form-control" value="<?= $recipe['prep_time'] ?>" required>
        </div>
        <div class="mb-3">
            <label for="cook_time" class="form-label">Cooking Time (minutes):</label>
            <input type="number" name="cook_time" class="form-control" value="<?= $recipe['cook_time'] ?>" required>
        </div>
        <div class="mb-3">
            <label for="difficulty" class="form-label">Difficulty:</label>
            <select name="difficulty" class="form-select" required>
                <option value="Easy" <?= $recipe['difficulty'] == 'Easy' ? 'selected' : '' ?>>Easy</option>
                <option value="Medium" <?= $recipe['difficulty'] == 'Medium' ? 'selected' : '' ?>>Medium</option>
                <option value="Hard" <?= $recipe['difficulty'] == 'Hard' ? 'selected' : '' ?>>Hard</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="portions" class="form-label">Portions:</label>
            <input type="number" name="portions" class="form-control" value="<?= $recipe['portions'] ?>" required>
        </div>
        <div class="mb-3">
            <label for="calories_per_portion" class="form-label">Calories per Portion:</label>
            <input type="number" name="calories_per_portion" class="form-control" value="<?= $recipe['calories_per_portion'] ?>" required>
        </div>
        <div class="mb-3">
            <label for="oven_temperature" class="form-label">Oven Temperature (°C):</label>
            <input type="number" name="oven_temperature" class="form-control" value="<?= $recipe['oven_temperature'] ?>">
        </div>
        <div class="mb-3">
            <label for="airfryer_temperature" class="form-label">Airfryer Temperature (°C):</label>
            <input type="number" name="airfryer_temperature" class="form-control" value="<?= $recipe['airfryer_temperature'] ?>">
        </div>

        <!-- Sección de Ingredientes -->
        <div id="ingredients-container" class="mb-3">
            <h4>Ingredients</h4>
            <?php while ($ingredient = $ingredients_result->fetch_assoc()): ?>
                <div class="ingredient-row row g-3 align-items-end mb-3">
                    <div class="col-md-4">
                        <label for="ingredients[<?= $ingredient['recipe_ingredient_id'] ?>][name]" class="form-label">Ingredient Name:</label>
                        <input list="ingredients-list" name="ingredients[<?= $ingredient['recipe_ingredient_id'] ?>][name]" class="form-control" value="<?= htmlspecialchars($ingredient['name']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="ingredients[<?= $ingredient['recipe_ingredient_id'] ?>][quantity]" class="form-label">Quantity:</label>
                        <input type="number" step="any" name="ingredients[<?= $ingredient['recipe_ingredient_id'] ?>][quantity]" class="form-control" value="<?= $ingredient['quantity'] ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="ingredients[<?= $ingredient['recipe_ingredient_id'] ?>][unit]" class="form-label">Unit:</label>
                        <input type="text" name="ingredients[<?= $ingredient['recipe_ingredient_id'] ?>][unit]" class="form-control" value="<?= $ingredient['unit'] ?>" required>
                    </div>
                    <div class="col-md-2">
                        <input type="checkbox" name="delete_ingredients[]" value="<?= $ingredient['recipe_ingredient_id'] ?>"> Delete
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <button type="button" class="btn btn-secondary mb-3" onclick="addIngredient()">Add Ingredient</button>

        <button type="submit" class="btn btn-primary w-100">Update Recipe</button>
    </form>
    
    <!-- Lista de ingredientes para autocompletado -->
    <datalist id="ingredients-list">
        <?php foreach ($all_ingredients as $ingredient): ?>
            <option value="<?= htmlspecialchars($ingredient['name']) ?>"></option>
        <?php endforeach; ?>
    </datalist>
    
    <p class="mt-3 text-center">
        <a href="home.php" class="btn btn-secondary btn-block">Back to Home</a>
    </p>
</body>
</html>
