<?php
require_once __DIR__ . '/../includes/session.php';

if (!isset($_GET['id'])) {
    die("Recipe ID is required.");
}

$recipe_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM recipes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $recipe_id, $_SESSION['user_id']);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();

if (!$recipe) {
    die("Recipe not found.");
}

$ingredients_stmt = $conn->prepare("
    SELECT ri.id as recipe_ingredient_id, i.name, ri.quantity, ri.unit
    FROM recipe_ingredients ri
    JOIN ingredients i ON ri.ingredient_id = i.id
    WHERE ri.recipe_id = ?");
$ingredients_stmt->bind_param("i", $recipe_id);
$ingredients_stmt->execute();
$ingredients_result = $ingredients_stmt->get_result();

$recipe_categories_stmt = $conn->prepare("SELECT category_id FROM recipe_categories WHERE recipe_id = ?");
$recipe_categories_stmt->bind_param("i", $recipe_id);
$recipe_categories_stmt->execute();
$selected_categories = array_column($recipe_categories_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'category_id');

$categories_result = $conn->query("SELECT id, name FROM categories");

$all_ingredients = $conn->query("SELECT id, name FROM ingredients ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars($_POST['name']);
    $description = htmlspecialchars($_POST['description']);
    $categories = $_POST['categories'];
    $prep_time = (int)$_POST['prep_time'];
    $cook_time = (int)$_POST['cook_time'];
    $difficulty = $_POST['difficulty'];
    $portions = (int)$_POST['portions'];
    $calories_per_portion = (int)$_POST['calories_per_portion'];
    $oven_temperature = !empty($_POST['oven_temperature']) ? (int)$_POST['oven_temperature'] : NULL;
    $airfryer_temperature = !empty($_POST['airfryer_temperature']) ? (int)$_POST['airfryer_temperature'] : NULL;
    $prep_ahead = !empty($_POST['prep_ahead']) ? $_POST['prep_ahead'] : NULL;

    $conn->begin_transaction();
    try {
        $update_stmt = $conn->prepare("UPDATE recipes SET name=?, description=?, prep_time=?, cook_time=?, difficulty=?, portions=?, calories_per_portion=?, oven_temperature=?, airfryer_temperature=?, prep_ahead=? WHERE id=? AND user_id=?");
        $update_stmt->bind_param("ssiisiiiisii", $name, $description, $prep_time, $cook_time, $difficulty, $portions, $calories_per_portion, $oven_temperature, $airfryer_temperature, $prep_ahead, $recipe_id, $_SESSION['user_id']);
        $update_stmt->execute();

        $conn->prepare("DELETE FROM recipe_categories WHERE recipe_id = ?")->bind_param("i", $recipe_id);
        $delete_cat = $conn->prepare("DELETE FROM recipe_categories WHERE recipe_id = ?");
        $delete_cat->bind_param("i", $recipe_id);
        $delete_cat->execute();

        $insert_cat = $conn->prepare("INSERT INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)");
        foreach ($categories as $cat_id) {
            $insert_cat->bind_param("ii", $recipe_id, $cat_id);
            $insert_cat->execute();
        }

        $delete_ing = $conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?");
        $delete_ing->bind_param("i", $recipe_id);
        $delete_ing->execute();

        $insert_ing = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit) VALUES (?, ?, ?, ?)");
        foreach ($_POST['ingredients'] as $ingredient) {
            if (!empty($ingredient['name']) && $ingredient['quantity'] > 0 && !empty($ingredient['unit'])) {
                $ingredient_name = htmlspecialchars($ingredient['name']);
                $quantity = (float)$ingredient['quantity'];
                $unit = htmlspecialchars($ingredient['unit']);

                $find = $conn->prepare("SELECT id FROM ingredients WHERE name = ?");
                $find->bind_param("s", $ingredient_name);
                $find->execute();
                $find->store_result();

                if ($find->num_rows > 0) {
                    $find->bind_result($ingredient_id);
                    $find->fetch();
                } else {
                    $ins = $conn->prepare("INSERT INTO ingredients (name) VALUES (?)");
                    $ins->bind_param("s", $ingredient_name);
                    $ins->execute();
                    $ingredient_id = $ins->insert_id;
                }

                $insert_ing->bind_param("iids", $recipe_id, $ingredient_id, $quantity, $unit);
                $insert_ing->execute();
            }
        }

        $conn->commit();
        header('Location: ' . BASE_URL . '/recipes/details.php?id=' . $recipe_id);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: Could not update recipe. " . $e->getMessage();
    }
}

$pageTitle = 'Edit Recipe';
include __DIR__ . '/../includes/header.php';
?>

<h2 class="text-center">✏️ Edit Recipe</h2>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form action="<?= BASE_URL ?>/recipes/edit.php?id=<?= $recipe_id ?>" method="POST" class="mt-4">
    <div class="mb-3">
        <label class="form-label">Recipe Name:</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($recipe['name']) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Description:</label>
        <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($recipe['description']) ?></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">Categories:</label>
        <div class="border rounded p-3 d-flex flex-wrap gap-3">
            <?php while ($category = $categories_result->fetch_assoc()): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="categories[]"
                           value="<?= $category['id'] ?>" id="cat-<?= $category['id'] ?>"
                           <?= in_array($category['id'], $selected_categories) ? 'checked' : '' ?>>
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
            <input type="number" name="prep_time" class="form-control" value="<?= $recipe['prep_time'] ?>" required>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Cooking Time (min):</label>
            <input type="number" name="cook_time" class="form-control" value="<?= $recipe['cook_time'] ?>" required>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Difficulty:</label>
            <select name="difficulty" class="form-select" required>
                <option value="Easy" <?= $recipe['difficulty'] == 'Easy' ? 'selected' : '' ?>>Easy</option>
                <option value="Medium" <?= $recipe['difficulty'] == 'Medium' ? 'selected' : '' ?>>Medium</option>
                <option value="Hard" <?= $recipe['difficulty'] == 'Hard' ? 'selected' : '' ?>>Hard</option>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Portions:</label>
            <input type="number" name="portions" class="form-control" value="<?= $recipe['portions'] ?>" required>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Calories per Portion:</label>
            <input type="number" name="calories_per_portion" class="form-control" value="<?= $recipe['calories_per_portion'] ?>" required>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Oven Temperature (°C):</label>
            <input type="number" name="oven_temperature" class="form-control" value="<?= $recipe['oven_temperature'] ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Airfryer Temperature (°C):</label>
            <input type="number" name="airfryer_temperature" class="form-control" value="<?= $recipe['airfryer_temperature'] ?>">
        </div>
    </div>

    <div id="ingredients-container" class="mb-3">
        <h4>Ingredients</h4>
        <?php while ($ingredient = $ingredients_result->fetch_assoc()): ?>
            <div class="ingredient-row row g-3 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label">Ingredient Name:</label>
                    <input list="ingredients-list" name="ingredients[<?= $ingredient['recipe_ingredient_id'] ?>][name]" class="form-control" value="<?= htmlspecialchars($ingredient['name']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantity:</label>
                    <input type="number" step="any" name="ingredients[<?= $ingredient['recipe_ingredient_id'] ?>][quantity]" class="form-control" value="<?= $ingredient['quantity'] ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit:</label>
                    <input type="text" name="ingredients[<?= $ingredient['recipe_ingredient_id'] ?>][unit]" class="form-control" value="<?= htmlspecialchars($ingredient['unit']) ?>" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger" onclick="removeIngredient(this)">Remove</button>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    <div class="mb-3 mt-2">
        <label class="form-label">🕐 Prep day before:</label>
        <textarea name="prep_ahead" class="form-control" rows="3"
                  placeholder="What to prepare the day before (optional)"><?= htmlspecialchars($recipe['prep_ahead'] ?? '') ?></textarea>
        <div class="form-text">Leave empty or generate automatically from the recipe detail page.</div>
    </div>

    <button type="button" class="btn btn-secondary mb-3" onclick="addIngredient()">Add Ingredient</button>
    <button type="submit" class="btn btn-primary w-100">Update Recipe</button>
</form>

<datalist id="ingredients-list">
    <?php foreach ($all_ingredients as $ingredient): ?>
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
