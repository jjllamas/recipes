<?php
require_once __DIR__ . '/../includes/session.php';

$selected_week = isset($_GET['week']) ? (int)$_GET['week'] : date('W');
$current_year  = date('Y');

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

$meal_types = [
    'Breakfast'       => 'bg-primary text-white',
    'Second Breakfast'=> 'bg-secondary text-white',
    'Lunch'           => 'bg-success text-white',
    'Afternoon Snack' => 'bg-warning text-dark',
    'Dinner'          => 'bg-danger text-white'
];

$stmt = $conn->prepare("SELECT id, name FROM recipes WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$menu_stmt = $conn->prepare("
    SELECT mp.*, r.name as recipe_name
    FROM menu_planner mp
    JOIN recipes r ON mp.recipe_id = r.id
    WHERE mp.week = ? AND mp.year = ? AND mp.user_id = ?");
$menu_stmt->bind_param("iii", $selected_week, $current_year, $_SESSION['user_id']);
$menu_stmt->execute();

$menu_data = [];
$menu_result = $menu_stmt->get_result();
while ($row = $menu_result->fetch_assoc()) {
    $menu_data[$row['day']][$row['meal_type']][] = ['recipe_name' => $row['recipe_name'], 'id' => $row['id']];
}
$menu_stmt->close();

if (isset($_POST['fill_gaps'])) {
    $conn->begin_transaction();
    try {
        $category_map = [
            'Breakfast'       => ['Breakfast'],
            'Second Breakfast'=> ['Snack'],
            'Lunch'           => ['Main dish', 'Side Dish', 'Dessert'],
            'Afternoon Snack' => ['Snack'],
            'Dinner'          => ['Main dish', 'Side Dish', 'Dessert']
        ];
        foreach ($days_of_week as $day) {
            foreach (array_keys($meal_types) as $meal_type) {
                if (empty($menu_data[$day][$meal_type])) {
                    foreach ($category_map[$meal_type] as $category) {
                        $s = $conn->prepare("SELECT id FROM recipes WHERE category_id = (SELECT id FROM categories WHERE name = ?) AND user_id = ? ORDER BY RAND() LIMIT 1");
                        $s->bind_param("si", $category, $_SESSION['user_id']);
                        $s->execute();
                        $res = $s->get_result();
                        if ($res->num_rows > 0) {
                            $recipe_id = $res->fetch_assoc()['id'];
                            $ins = $conn->prepare("INSERT INTO menu_planner (user_id, week, year, day, meal_type, recipe_id) VALUES (?, ?, ?, ?, ?, ?)");
                            $ins->bind_param("iiissi", $_SESSION['user_id'], $selected_week, $current_year, $day, $meal_type, $recipe_id);
                            $ins->execute();
                        }
                    }
                }
            }
        }
        $conn->commit();
        header('Location: ' . BASE_URL . '/menu/planner.php?week=' . $selected_week);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error filling gaps: " . $e->getMessage();
    }
}

$pageTitle = 'Menu Planner — Accordion';
$extraHead = '
<style>
    .meal-header { font-weight:bold; padding:8px; border-bottom:1px solid #dee2e6; }
    .meal-content { padding:8px; border-bottom:1px solid #dee2e6; }
    .meal-container { border:1px solid #dee2e6; margin-bottom:5px; border-radius:5px; box-shadow:0 2px 5px rgba(0,0,0,.1); }
    .add-recipe-form { display:flex; gap:8px; align-items:center; margin-top:8px; }
</style>';
include __DIR__ . '/../includes/header.php';
?>

<h2 class="text-center">🗓️ Weekly Menu Planner</h2>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="GET" class="mb-4">
    <div class="row justify-content-center">
        <div class="col-auto">
            <label for="week" class="form-label">Select Week:</label>
            <input type="number" name="week" id="week" class="form-control" min="1" max="53" value="<?= $selected_week ?>" required>
        </div>
        <div class="col-auto align-self-end">
            <button type="submit" class="btn btn-primary">Go</button>
        </div>
    </div>
</form>

<div class="accordion" id="menuAccordion">
    <?php foreach ($days_of_week as $index => $day): ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?= $index ?>">
                <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>">
                    <?= $day ?>
                </button>
            </h2>
            <div id="collapse<?= $index ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#menuAccordion">
                <div class="accordion-body">
                    <?php foreach ($meal_types as $meal => $color): ?>
                        <div class="meal-container mb-2">
                            <div class="meal-header <?= $color ?> d-flex justify-content-between align-items-center">
                                <span><?= $meal ?></span>
                                <form action="<?= BASE_URL ?>/api/add_to_menu.php" method="POST" class="add-recipe-form" onsubmit="return assignRecipeId(this)">
                                    <input list="recipe-list" name="recipe_name" class="form-control form-control-sm" placeholder="Search recipe..." required>
                                    <input type="hidden" name="recipe_id">
                                    <input type="hidden" name="week" value="<?= $selected_week ?>">
                                    <input type="hidden" name="year" value="<?= $current_year ?>">
                                    <input type="hidden" name="day" value="<?= $day ?>">
                                    <input type="hidden" name="meal_type" value="<?= $meal ?>">
                                    <button type="submit" class="btn btn-light btn-sm text-dark">ADD</button>
                                </form>
                            </div>
                            <div class="meal-content">
                                <?php if (isset($menu_data[$day][$meal])): ?>
                                    <ul class="list-group mb-0">
                                        <?php foreach ($menu_data[$day][$meal] as $recipe): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?= htmlspecialchars($recipe['recipe_name']) ?>
                                                <form action="<?= BASE_URL ?>/api/delete_from_menu.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="menu_planner_id" value="<?= $recipe['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No recipes added.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<datalist id="recipe-list">
    <?php foreach ($recipes as $recipe): ?>
        <option value="<?= htmlspecialchars($recipe['name']) ?>" data-id="<?= $recipe['id'] ?>"></option>
    <?php endforeach; ?>
</datalist>

<div class="d-flex gap-2 justify-content-center mt-4">
    <form method="POST">
        <button type="submit" name="fill_gaps" class="btn btn-info">🔄 Fill Gaps with Suggestions</button>
    </form>
    <form action="<?= BASE_URL ?>/menu/shopping_list.php" method="POST">
        <input type="hidden" name="week" value="<?= $selected_week ?>">
        <button type="submit" class="btn btn-info">🛒 Generate Shopping List</button>
    </form>
</div>

<script>
    function assignRecipeId(form) {
        const input = form.querySelector('input[name="recipe_name"]');
        const recipeIdInput = form.querySelector('input[name="recipe_id"]');
        const option = Array.from(document.getElementById('recipe-list').options).find(o => o.value === input.value);
        if (option) { recipeIdInput.value = option.dataset.id; return true; }
        alert('Please select a valid recipe from the list.');
        return false;
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
