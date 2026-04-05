<?php
require_once __DIR__ . '/../includes/session.php';

$categories_result = $conn->query("SELECT id, name FROM categories");

$ingredients_result = $conn->query("SELECT id, name FROM ingredients ORDER BY name ASC");
$ingredients_list = [];
while ($row = $ingredients_result->fetch_assoc()) {
    $ingredients_list[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars($_POST['name']);
    $description = htmlspecialchars($_POST['description']);
    $categories = $_POST['categories'];
    $category_id = (int)$categories[0];
    $prep_time = (int)$_POST['prep_time'];
    $cook_time = (int)$_POST['cook_time'];
    $difficulty = $_POST['difficulty'];
    $portions = (int)$_POST['portions'];
    $calories_per_portion = (int)$_POST['calories_per_portion'];
    $oven_temperature = !empty($_POST['oven_temperature']) ? (int)$_POST['oven_temperature'] : NULL;
    $airfryer_temperature = !empty($_POST['airfryer_temperature']) ? (int)$_POST['airfryer_temperature'] : NULL;
    $user_id = $_SESSION['user_id'];
    $ingredients = $_POST['ingredients'];

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
            $stmt = $conn->prepare("INSERT INTO recipes (user_id, name, description, category_id, prep_time, cook_time, difficulty, portions, calories_per_portion, oven_temperature, airfryer_temperature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issiiisiiii", $user_id, $name, $description, $category_id, $prep_time, $cook_time, $difficulty, $portions, $calories_per_portion, $oven_temperature, $airfryer_temperature);
            $stmt->execute();
            $recipe_id = $stmt->insert_id;

            $stmt = $conn->prepare("INSERT INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)");
            foreach ($categories as $cat_id) {
                $stmt->bind_param("ii", $recipe_id, $cat_id);
                $stmt->execute();
            }

            $stmt = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES (?, ?, ?, ?)");
            foreach ($ingredients as $ingredient) {
                $ingredient_name = htmlspecialchars($ingredient['name']);
                $quantity = (float)$ingredient['quantity'];
                $unit = htmlspecialchars($ingredient['unit']);

                $ingredient_stmt = $conn->prepare("SELECT id FROM ingredients WHERE name = ?");
                $ingredient_stmt->bind_param("s", $ingredient_name);
                $ingredient_stmt->execute();
                $ingredient_stmt->store_result();

                if ($ingredient_stmt->num_rows > 0) {
                    $ingredient_stmt->bind_result($ingredient_id);
                    $ingredient_stmt->fetch();
                } else {
                    $ingredient_stmt = $conn->prepare("INSERT INTO ingredients (name) VALUES (?)");
                    $ingredient_stmt->bind_param("s", $ingredient_name);
                    $ingredient_stmt->execute();
                    $ingredient_id = $ingredient_stmt->insert_id;
                }

                $stmt->bind_param("iids", $recipe_id, $ingredient_id, $quantity, $unit);
                $stmt->execute();
            }

            $conn->commit();
            header('Location: ' . BASE_URL . '/recipes/');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: Could not create recipe. " . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Recipe';
include __DIR__ . '/../includes/header.php';
?>

<h2 class="text-center">🍲 Create a New Recipe</h2>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form action="<?= BASE_URL ?>/recipes/create.php" method="POST" class="mt-4">
    <div class="mb-3">
        <label class="form-label">Recipe Name:</label>
        <input type="text" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Description:</label>
        <textarea name="description" class="form-control" rows="4" required></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">Categories:</label>
        <div class="border rounded p-3 d-flex flex-wrap gap-3">
            <?php while ($category = $categories_result->fetch_assoc()): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="categories[]"
                           value="<?= $category['id'] ?>" id="cat-<?= $category['id'] ?>">
                    <label class="form-check-label" for="cat-<?= $category['id'] ?>">
                        <?= htmlspecialchars($category['name']) ?>
                    </label>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Preparation Time (min):</label>
            <input type="number" name="prep_time" class="form-control" required>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Cooking Time (min):</label>
            <input type="number" name="cook_time" class="form-control" required>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Difficulty:</label>
            <select name="difficulty" class="form-select" required>
                <option value="Easy">Easy</option>
                <option value="Medium">Medium</option>
                <option value="Hard">Hard</option>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Portions:</label>
            <input type="number" name="portions" class="form-control" required>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Calories per Portion:</label>
            <input type="number" name="calories_per_portion" class="form-control" required>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Oven Temperature (°C):</label>
            <input type="number" name="oven_temperature" class="form-control">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Airfryer Temperature (°C):</label>
            <input type="number" name="airfryer_temperature" class="form-control">
        </div>
    </div>

    <div id="ingredients-container" class="mb-3">
        <h4>Ingredients</h4>
        <div class="ingredient-row row g-3 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label">Ingredient Name:</label>
                <input list="ingredients-list" name="ingredients[0][name]" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantity:</label>
                <input type="number" step="any" name="ingredients[0][quantity]" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Unit:</label>
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

<datalist id="ingredients-list">
    <?php foreach ($ingredients_list as $ingredient): ?>
        <option value="<?= htmlspecialchars($ingredient['name']) ?>"></option>
    <?php endforeach; ?>
</datalist>

<script>
    function addIngredient() {
        const index = document.querySelectorAll('.ingredient-row').length;
        const tpl = `
            <div class="ingredient-row row g-3 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label">Ingredient Name:</label>
                    <input list="ingredients-list" name="ingredients[${index}][name]" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantity:</label>
                    <input type="number" step="any" name="ingredients[${index}][quantity]" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit:</label>
                    <input type="text" name="ingredients[${index}][unit]" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger" onclick="removeIngredient(this)">Remove</button>
                </div>
            </div>`;
        document.getElementById('ingredients-container').insertAdjacentHTML('beforeend', tpl);
    }

    function removeIngredient(button) {
        button.closest('.ingredient-row').remove();
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
