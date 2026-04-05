<?php
require_once __DIR__ . '/../includes/session.php';

$current_week = isset($_GET['week']) ? (int)$_GET['week'] : date('W');
$current_year = date('Y');

$weekStart = new DateTime();
$weekStart->setISODate($current_year, $current_week, 1);
$weekEnd = new DateTime();
$weekEnd->setISODate($current_year, $current_week, 7);
$dateRange = $weekStart->format('d M') . ' – ' . $weekEnd->format('d M');

// Días con su fecha real
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$day_dates = [];
foreach ($days_of_week as $i => $day) {
    $d = clone $weekStart;
    $d->modify("+{$i} days");
    $day_dates[$day] = $d->format('d M');
}

// Colores por tipo de comida
$meal_colors = [
    'Breakfast'       => ['bg' => '#cfe2ff', 'text' => '#084298'],
    'Second Breakfast'=> ['bg' => '#e2d9f3', 'text' => '#432874'],
    'Lunch'           => ['bg' => '#d1e7dd', 'text' => '#0a3622'],
    'Afternoon Snack' => ['bg' => '#fff3cd', 'text' => '#664d03'],
    'Dinner'          => ['bg' => '#f8d7da', 'text' => '#58151c'],
];

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
    .table-summary { border-collapse: separate; border-spacing: 0; }
    .table-summary thead th {
        text-align: center;
        font-size: .8rem;
        font-weight: 600;
        padding: 8px 6px;
        border: 1px solid #dee2e6;
        white-space: nowrap;
    }
    .table-summary tbody td { border: 1px solid #dee2e6; vertical-align: top; padding: 6px; }
    .day-cell {
        font-weight: 600;
        font-size: .85rem;
        text-align: center;
        vertical-align: middle !important;
        white-space: nowrap;
        min-width: 54px;
    }
    .weekend-row .day-cell { background-color: #e8d5f5 !important; }
    .weekend-row td { background-color: #faf5ff; }
    .day-name { display: block; }
    .day-date { display: block; font-size: .75rem; font-weight: 400; color: #6c757d; }
    .today-row .day-cell { background-color: #fff3cd !important; }
    .recipe-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #e9ecef;
        border-radius: 20px;
        padding: 2px 8px 2px 10px;
        font-size: .78rem;
        margin: 2px 2px;
        max-width: 100%;
    }
    .recipe-pill a {
        color: #212529;
        text-decoration: none;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 120px;
    }
    .recipe-pill a:hover { text-decoration: underline; }
    .recipe-pill .pill-delete {
        cursor: pointer;
        color: #adb5bd;
        font-size: .7rem;
        line-height: 1;
        flex-shrink: 0;
        border: none;
        background: none;
        padding: 0;
    }
    .recipe-pill .pill-delete:hover { color: #dc3545; }
    .empty-cell { color: #ced4da; font-size: .75rem; text-align: center; padding: 4px 0; }
    .datalist-container input { font-size: .78rem; }
    @media (max-width: 768px) {
        .table-summary { font-size: .72rem; }
        .recipe-pill a { max-width: 70px; }
    }
</style>';
include __DIR__ . '/../includes/header.php';

// Detectar día actual para marcarlo
$today = (new DateTime())->format('l'); // 'Monday', 'Tuesday'...
$todayWeek = (int)(new DateTime())->format('W');
$todayYear = (int)(new DateTime())->format('Y');
$isCurrentWeek = ($todayWeek === $current_week && $todayYear === $current_year);
?>

<h1 class="text-center">🍽️ Weekly Menu Planner</h1>

<div class="text-center my-3">
    <?php $prev = max(1, $current_week - 1); $next = min(53, $current_week + 1); ?>
    <a href="?week=<?= $prev ?>" class="btn btn-outline-secondary btn-sm <?= $current_week <= 1 ? 'disabled' : '' ?>">
        <i class="bi bi-chevron-left"></i>
    </a>
    <span class="mx-3 fw-semibold">
        Week <?= $current_week ?> &nbsp;·&nbsp;
        <span class="text-muted fw-normal"><?= $dateRange ?></span>
    </span>
    <a href="?week=<?= $next ?>" class="btn btn-outline-secondary btn-sm <?= $current_week >= 53 ? 'disabled' : '' ?>">
        <i class="bi bi-chevron-right"></i>
    </a>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-sm table-summary">
        <thead>
            <tr>
                <th style="background:#f8f9fa; min-width:54px;"></th>
                <?php foreach ($meal_colors as $meal => $color): ?>
                    <th style="background-color:<?= $color['bg'] ?>; color:<?= $color['text'] ?>;">
                        <?= $meal ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($days_of_week as $day):
                $isToday   = $isCurrentWeek && $day === $today;
                $isWeekend = in_array($day, ['Saturday', 'Sunday']);
            ?>
                <tr class="<?= $isToday ? 'today-row' : ($isWeekend ? 'weekend-row' : '') ?>">
                    <td class="day-cell" style="background:#f8f9fa;">
                        <span class="day-name"><?= substr($day, 0, 3) ?></span>
                        <span class="day-date"><?= $day_dates[$day] ?></span>
                        <?php if ($isToday): ?>
                            <span class="badge bg-warning text-dark" style="font-size:.6rem;">Today</span>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($meal_types as $meal => $categories):
                        $color = $meal_colors[$meal];
                        $hasRecipes = isset($menu_data[$day][$meal]);
                    ?>
                        <td>
                            <div class="recipe-list d-flex flex-wrap align-items-start">
                                <?php if ($hasRecipes): ?>
                                    <?php foreach ($menu_data[$day][$meal] as $recipe): ?>
                                        <span class="recipe-pill">
                                            <a href="<?= BASE_URL ?>/recipes/details.php?id=<?= $recipe['recipe_id'] ?>"
                                               title="<?= htmlspecialchars($recipe['recipe_name']) ?>">
                                                <?= htmlspecialchars($recipe['recipe_name']) ?>
                                            </a>
                                            <button class="pill-delete"
                                                    onclick="deleteRecipe(<?= $recipe['menu_planner_id'] ?>, this)"
                                                    title="Remove">✕</button>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="empty-cell w-100">— empty —</span>
                                <?php endif; ?>
                            </div>
                            <div class="datalist-container mt-1">
                                <input list="recipes-<?= $meal ?>-list"
                                       class="form-control form-control-sm"
                                       placeholder="Add recipe…"
                                       oninput="assignRecipeId(this)"
                                       onchange="addRecipe('<?= $day ?>', '<?= $meal ?>', this)">
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
    <a href="<?= BASE_URL ?>/recipes/" class="btn btn-primary"><i class="bi bi-book"></i> Recipe Library</a>
    <a href="<?= BASE_URL ?>/menu/planner.php?week=<?= $current_week ?>" class="btn btn-secondary"><i class="bi bi-calendar3"></i> Accordion View</a>
    <form action="<?= BASE_URL ?>/menu/shopping_list.php" method="POST">
        <input type="hidden" name="week" value="<?= $current_week ?>">
        <button type="submit" class="btn btn-info"><i class="bi bi-cart3"></i> Generate Shopping List</button>
    </form>
    <a href="<?= BASE_URL ?>/menu/print.php?week=<?= $current_week ?>" class="btn btn-outline-dark" target="_blank">
        <i class="bi bi-printer"></i> Print Menu
    </a>
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
            if (xhr.readyState !== 4) return;
            if (xhr.status === 200 && xhr.responseText.includes("successfully")) {
                const recipeList = inputElement.closest('td').querySelector('.recipe-list');
                // Quitar el "— empty —" si existe
                const emptySpan = recipeList.querySelector('.empty-cell');
                if (emptySpan) emptySpan.remove();

                const pill = document.createElement('span');
                pill.className = 'recipe-pill';
                pill.innerHTML = `<a href="<?= BASE_URL ?>/recipes/details.php?id=${recipeId}" title="${recipeName}">${recipeName}</a><button class="pill-delete" onclick="deleteRecipe(${recipeId}, this)" title="Remove">✕</button>`;
                recipeList.appendChild(pill);
                inputElement.value = '';
                showToast(`"${recipeName}" added to ${mealType}`);
            } else {
                showToast('Failed to add recipe. Try again.', 'error');
            }
        };
        xhr.send(`recipe_id=${recipeId}&day=${day}&meal_type=${mealType}&week=<?= $current_week ?>&year=<?= $current_year ?>`);
    }

    function deleteRecipe(menuPlannerId, element) {
        if (!confirm('Are you sure you want to remove this recipe?')) return;
        const pill = element.closest('.recipe-pill');
        const recipeName = pill.querySelector('a')?.textContent.trim() ?? 'Recipe';
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "<?= BASE_URL ?>/api/delete_from_menu.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            if (xhr.status === 200 && xhr.responseText.includes("successfully")) {
                const recipeList = pill.closest('.recipe-list');
                pill.remove();
                // Si ya no quedan pills, mostrar "— empty —"
                if (!recipeList.querySelector('.recipe-pill')) {
                    recipeList.insertAdjacentHTML('beforeend', '<span class="empty-cell w-100">— empty —</span>');
                }
                showToast(`"${recipeName}" removed from menu`);
            } else {
                showToast('Failed to remove recipe. Try again.', 'error');
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
