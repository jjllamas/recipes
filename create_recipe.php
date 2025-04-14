<?php
include 'includes/db_connect.php';
include 'web_session_start.php';

// Consultar categorías desde la base de datos
$query = "SELECT id, name FROM categories";
$categories_result = $conn->query($query);

// Obtener todos los ingredientes para autocompletado
$ingredients_query = "SELECT id, name FROM ingredients ORDER BY name ASC";
$ingredients_result = $conn->query($ingredients_query);

// Inicializar array de ingredientes
$ingredients_list = [];
while ($row = $ingredients_result->fetch_assoc()) {
    $ingredients_list[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = htmlspecialchars($_POST['name']);
    $description = htmlspecialchars($_POST['description']);
    $categories = $_POST['categories']; // Array de categorías seleccionadas
    $category_id = (int)$categories[0]; // Usar la primera categoría seleccionada para `category_id`
    $prep_time = (int)$_POST['prep_time'];
    $cook_time = (int)$_POST['cook_time'];
    $difficulty = $_POST['difficulty'];
    $portions = (int)$_POST['portions'];
    $calories_per_portion = (int)$_POST['calories_per_portion'];
    $oven_temperature = !empty($_POST['oven_temperature']) ? (int)$_POST['oven_temperature'] : NULL;
    $airfryer_temperature = !empty($_POST['airfryer_temperature']) ? (int)$_POST['airfryer_temperature'] : NULL;
    $user_id = $_SESSION['user_id'];
    $ingredients = $_POST['ingredients']; // Array de ingredientes

    // Validación de Ingredientes
    $invalid_ingredient = false;
    foreach ($ingredients as $ingredient) {
        if (empty(trim($ingredient['name'])) || $ingredient['quantity'] <= 0 || empty(trim($ingredient['unit']))) {
            $invalid_ingredient = true;
            break;
        }
    }

    if ($invalid_ingredient) {
        $error = "Each ingredient must have a name, a positive quantity, and a unit.";
    } else {
        $conn->begin_transaction();
        try {
            // Insertar la receta con la primera categoría seleccionada como `category_id`
            $stmt = $conn->prepare("INSERT INTO recipes (user_id, name, description, category_id, prep_time, cook_time, difficulty, portions, calories_per_portion, oven_temperature, airfryer_temperature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issiiisiiii", $user_id, $name, $description, $category_id, $prep_time, $cook_time, $difficulty, $portions, $calories_per_portion, $oven_temperature, $airfryer_temperature);
            $stmt->execute();
            $recipe_id = $stmt->insert_id; // Obtener el ID de la receta recién creada

            // Insertar categorías en la tabla de relación
            $stmt = $conn->prepare("INSERT INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)");
            foreach ($categories as $category_id) {
                $stmt->bind_param("ii", $recipe_id, $category_id);
                $stmt->execute();
            }

            // Insertar ingredientes
            $stmt = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES (?, ?, ?, ?)");
            foreach ($ingredients as $ingredient) {
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
                $stmt->bind_param("iids", $recipe_id, $ingredient_id, $quantity, $unit);
                $stmt->execute();
            }

            // Commit de la transacción
            $conn->commit();
            header("Location: list_recipes.php");
            exit();
        } catch (Exception $e) {
            // Rollback en caso de error
            $conn->rollback();
            $error = "Error: Could not create recipe. " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Recipe</title>
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
    <h2 class="text-center">🍲 Create a New Recipe</h2>
    <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form action="create_recipe.php" method="POST" class="mt-4">
        <div class="mb-3">
            <label for="name" class="form-label">Recipe Name:</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description:</label>
            <textarea name="description" class="form-control" rows="4" required></textarea>
        </div>
        <div class="mb-3">
            <label for="categories" class="form-label">Categories:</label>
            <select name="categories[]" class="form-select" multiple required>
                <?php while ($category = $categories_result->fetch_assoc()): ?>
                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="prep_time" class="form-label">Preparation Time (minutes):</label>
            <input type="number" name="prep_time" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="cook_time" class="form-label">Cooking Time (minutes):</label>
            <input type="number" name="cook_time" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="difficulty" class="form-label">Difficulty:</label>
            <select name="difficulty" class="form-select" required>
                <option value="Easy">Easy</option>
                <option value="Medium">Medium</option>
                <option value="Hard">Hard</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="portions" class="form-label">Portions:</label>
            <input type="number" name="portions" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="calories_per_portion" class="form-label">Calories per Portion:</label>
            <input type="number" name="calories_per_portion" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="oven_temperature" class="form-label">Oven Temperature (°C):</label>
            <input type="number" name="oven_temperature" class="form-control">
        </div>
        <div class="mb-3">
            <label for="airfryer_temperature" class="form-label">Airfryer Temperature (°C):</label>
            <input type="number" name="airfryer_temperature" class="form-control">
        </div>

        <!-- Sección de Ingredientes -->
        <div id="ingredients-container" class="mb-3">
            <h4>Ingredients</h4>
            <!-- Plantilla de ingrediente -->
            <div class="ingredient-row row g-3 align-items-end mb-3">
                <div class="col-md-4">
                    <label for="ingredients[0][name]" class="form-label">Ingredient Name:</label>
                    <input list="ingredients-list" name="ingredients[0][name]" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label for="ingredients[0][quantity]" class="form-label">Quantity:</label>
                    <input type="number" step="any" name="ingredients[0][quantity]" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label for="ingredients[0][unit]" class="form-label">Unit:</label>
                    <input type="text" name="ingredients[0][unit]" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger" onclick="removeIngredient(this)">Remove</button>
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-secondary mb-3" onclick="addIngredient()">Add Ingredient</button>

        <button type="submit" class="btn btn-primary w-100">Create Recipe</button>
    </form>
    
    <!-- Lista de ingredientes para autocompletado -->
    <datalist id="ingredients-list">
        <?php foreach ($ingredients_list as $ingredient): ?>
            <option value="<?= htmlspecialchars($ingredient['name']) ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <p class="mt-3 text-center">
        <a href="home.php" class="btn btn-secondary btn-block">Back to Home</a>
    </p>
</body>
</html>
