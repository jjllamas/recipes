<?php
require_once __DIR__ . '/../includes/session.php';

$current_week = isset($_GET['week']) ? (int)$_GET['week'] : date('W');
$current_year = date('Y');

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

$meal_types = [
    'Breakfast'       => ['Breakfast'],
    'Second Breakfast'=> ['Snack'],
    'Lunch'           => ['Main dish', 'Side dish', 'Dessert'],
    'Afternoon Snack' => ['Snack'],
    'Dinner'          => ['Main dish', 'Side dish', 'Dessert']
];

$recipes_stmt = $conn->prepare("
    SELECT r.id, r.name, c.name as category
    FROM recipes r
    JOIN recipe_categories rc ON r.id = rc.recipe_id
    JOIN categories c ON rc.category_id = c.id
    WHERE r.user_id = ?");
$recipes_stmt->bind_param("i", $_SESSION['user_id']);
$recipes_stmt->execute();

$recipes_by_category = [];
$recipes_result = $recipes_stmt->get_result();
while ($row = $recipes_result->fetch_assoc()) {
    $recipes_by_category[$row['category']][] = $row;
}
$recipes_stmt->close();

$menu_stmt = $conn->prepare("
    SELECT mp.*, r.id as recipe_id, r.name as recipe_name
    FROM menu_planner mp
    JOIN recipes r ON mp.recipe_id = r.id
    WHERE mp.week = ? AND mp.year = ? AND mp.user_id = ?");
$menu_stmt->bind_param("iii", $current_week, $current_year, $_SESSION['user_id']);
$menu_stmt->execute();

$menu_data = [];
$menu_result = $menu_stmt->get_result();
while ($row = $menu_result->fetch_assoc()) {
    $menu_data[$row['day']][$row['meal_type']][] = [
        'recipe_name'     => $row['recipe_name'],
        'recipe_id'       => $row['recipe_id'],
        'menu_planner_id' => $row['id']
    ];
}
$menu_stmt->close();

$pageTitle = 'Menu Planner';
$extraHead = '
<style>
    .table-summary thead th { background-color:#cfe2ff; color:#000; border:2px solid #000; }
    .table-summary tbody td, .table-summary tbody th { border:2px solid #000; }
    .table-summary tbody .day-cell { background-color:#d4edda; color:#000; }
    .table-summary tbody tr:hover { color:#fff; background-color:#6c757d; }
    .table-summary tbody tr:hover .day-cell { background-color:#82c79d !important; }
    .datalist-container { display:flex; flex-direction:column; justify-content:flex-end; }
    .delete-btn { color:red; cursor:pointer; margin-left:10px; }
    @media (max-width:768px) {
        .table-summary { font-size:.75rem; }
        th, td { white-space:nowrap; }
    }
</style>';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="text-center">🍽️ Weekly Menu Planner</h1>
<br>

<div class="text-center mb-4">
    <?php $prev = max(1, $current_week - 1); $next = min(53, $current_week + 1); ?>
    <a href="?week=<?= $prev ?>" class="btn btn-info btn-sm <?= $current_week <= 1 ? 'disabled' : '' ?>">Previous Week</a>
    <span class="mx-2">Week <?= $current_week ?></span>
    <a href="?week=<?= $next ?>" class="btn btn-info btn-sm <?= $current_week >= 53 ? 'disabled' : '' ?>">Next Week</a>
</div>

<h3>Weekly Summary — Week <?= $current_week ?></h3>
<div class="table-responsive">
    <table class="table table-bordered table-hover table-sm table-summary">
        <thead>
            <tr>
                <th>Day</th>
                <?php foreach ($meal_types as $meal => $categories): ?>
                    <th><?= $meal ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($days_of_week as $day): ?>
                <tr>
                    <td class="day-cell"><?= $day ?></td>
                    <?php foreach ($meal_types as $meal => $categories): ?>
                        <td>
                            <ul class="list-unstyled mb-0 recipe-list">
                                <?php if (isset($menu_data[$day][$meal])): ?>
                                    <?php foreach ($menu_data[$day][$meal] as $recipe): ?>
                                        <li>
                                            <a href="<?= BASE_URL ?>/recipes/details.php?id=<?= $recipe['recipe_id'] ?>">- <?= htmlspecialchars($recipe['recipe_name']) ?></a>
                                            <span class="delete-btn" onclick="deleteRecipe(<?= $recipe['menu_planner_id'] ?>, this)">❌</span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            <div class="datalist-container">
                                <input list="recipes-<?= $meal ?>-list" class="form-control form-control-sm mt-1" oninput="assignRecipeId(this)" onchange="addRecipe('<?= $day ?>', '<?= $meal ?>', this)">
                                <datalist id="recipes-<?= $meal ?>-list">
                                    <?php foreach ($categories as $category): ?>
                                        <?php foreach ($recipes_by_category[$category] ?? [] as $recipe): ?>
                                            <option value="<?= htmlspecialchars($recipe['name']) ?>" data-id="<?= $recipe['id'] ?>"></option>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="d-flex flex-wrap gap-2 mt-4 justify-content-center">
    <a href="<?= BASE_URL ?>/recipes/" class="btn btn-primary">📚 Recipe Library</a>
    <a href="<?= BASE_URL ?>/menu/planner.php?week=<?= $current_week ?>" class="btn btn-secondary">🗓️ Accordion View</a>
    <form action="<?= BASE_URL ?>/menu/shopping_list.php" method="POST">
        <input type="hidden" name="week" value="<?= $current_week ?>">
        <button type="submit" class="btn btn-info">🛒 Generate Shopping List</button>
    </form>
</div>

<script>
    function addRecipe(day, mealType, inputElement) {
        const recipeName = inputElement.value;
        let recipeId = null;
        for (const option of inputElement.list.options) {
            if (option.value === recipeName) {
                recipeId = option.getAttribute('data-id');
                break;
            }
        }
        if (!recipeId) return;

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "<?= BASE_URL ?>/api/add_to_menu.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200 && xhr.responseText.includes("successfully")) {
                const recipeList = inputElement.closest('td').querySelector('.recipe-list');
                const li = document.createElement('li');
                li.innerHTML = `<a href="<?= BASE_URL ?>/recipes/details.php?id=${recipeId}">- ${recipeName}</a> <span class="delete-btn" onclick="deleteRecipe(${recipeId}, this)">❌</span>`;
                recipeList.appendChild(li);
                inputElement.value = '';
            }
        };
        xhr.send(`recipe_id=${recipeId}&day=${day}&meal_type=${mealType}&week=<?= $current_week ?>&year=<?= $current_year ?>`);
    }

    function deleteRecipe(menuPlannerId, element) {
        if (!confirm('Are you sure you want to delete this recipe?')) return;
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "<?= BASE_URL ?>/api/delete_from_menu.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200 && xhr.responseText.includes("successfully")) {
                element.closest('li').remove();
            }
        };
        xhr.send(`menu_planner_id=${menuPlannerId}`);
    }

    function assignRecipeId(inputElement) {
        const option = document.querySelector(`#${inputElement.getAttribute('list')} option[value="${inputElement.value}"]`);
        if (option) inputElement.setAttribute("data-id", option.getAttribute('data-id'));
        else inputElement.removeAttribute("data-id");
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
