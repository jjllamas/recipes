<?php
include 'includes/db_connect.php';
include 'web_session_start.php';

// Obtener la semana actual o seleccionada
$current_week = isset($_GET['week']) ? (int)$_GET['week'] : date('W');
$current_year = date('Y');

// Días de la semana
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Tipos de comida mapeados a categorías correspondientes
$meal_types = [
    'Breakfast' => ['Breakfast'],
    'Second Breakfast' => ['Snack'],
    'Lunch' => ['Main dish', 'Side dish', 'Dessert'],
    'Afternoon Snack' => ['Snack'],
    'Dinner' => ['Main dish', 'Side dish', 'Dessert']
];

// Consultar recetas agrupadas por categoría
$recipes_query = "
    SELECT r.id, r.name, c.name as category 
    FROM recipes r 
    JOIN recipe_categories rc ON r.id = rc.recipe_id 
    JOIN categories c ON rc.category_id = c.id 
    WHERE r.user_id = ?";
$recipes_stmt = $conn->prepare($recipes_query);
$recipes_stmt->bind_param("i", $_SESSION['user_id']);
$recipes_stmt->execute();
$recipes_result = $recipes_stmt->get_result();

$recipes_by_category = [];
while ($row = $recipes_result->fetch_assoc()) {
    $recipes_by_category[$row['category']][] = $row;
}
$recipes_stmt->close();

// Consultar el menú semanal almacenado para la semana en curso
$menu_query = "
    SELECT mp.*, r.id as recipe_id, r.name as recipe_name 
    FROM menu_planner mp 
    JOIN recipes r ON mp.recipe_id = r.id 
    WHERE mp.week = ? AND mp.year = ? AND mp.user_id = ?";
$menu_stmt = $conn->prepare($menu_query);
$menu_stmt->bind_param("iii", $current_week, $current_year, $_SESSION['user_id']);
$menu_stmt->execute();
$menu_result = $menu_stmt->get_result();

$menu_data = [];
while ($row = $menu_result->fetch_assoc()) {
    $menu_data[$row['day']][$row['meal_type']][] = [
        'recipe_name' => $row['recipe_name'],
        'recipe_id' => $row['recipe_id'],
        'menu_planner_id' => $row['id'] 
    ];
}
$menu_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Home</title>
    <link rel="icon" href="favicon_recipe.jpg" type="image/jpeg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-summary thead th {
            background-color: #cfe2ff;
            color: #000;
            border: 2px solid #000;
        }
        .table-summary tbody td, .table-summary tbody th {
            border: 2px solid #000;
        }
        .table-summary tbody .day-cell {
            background-color: #d4edda;
            color: #000;
            border-left: 2px solid #000;
            border-right: 2px solid #000;
        }
        .table-summary tbody tr:hover {
            color: #fff;
            background-color: #6c757d;
        }
        .table-summary tbody tr:hover .day-cell {
            background-color: #82c79d !important;
        }
        .btn-group {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }
        .btn-group a {
            width: 48%;
        }
        @media (max-width: 768px) {
            .btn-group a {
                width: 100%;
            }
            .table-summary {
                font-size: 0.75rem;
            }
            th, td {
                white-space: nowrap;
            }
        }
        .datalist-container {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        /* Estilo para la X de eliminar */
        .delete-btn {
            color: red;
            cursor: pointer;
            margin-left: 10px;
        }
    </style>
    <script>
        function addRecipe(day, mealType, inputElement) {
            const recipeName = inputElement.value;
            const datalist = inputElement.list;

            let recipeId = null;
            for (const option of datalist.options) {
                if (option.value === recipeName) {
                    recipeId = option.getAttribute('data-id');
                    break;
                }
            }

            if (recipeId) {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "add_recipe_to_menu.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200 && xhr.responseText.includes("successfully")) {
                            const cell = inputElement.closest('td');
                            const recipeList = cell.querySelector('.recipe-list');
                            const li = document.createElement('li');
                            li.innerHTML = `<a href="recipe_details.php?id=${recipeId}">- ${recipeName}</a> <span class="delete-btn" onclick="deleteRecipe(${recipeId}, this)">❌</span>`;
                            recipeList.appendChild(li);
                            inputElement.value = '';
                        } else {
                            console.error("Error al guardar la receta:", xhr.responseText);
                        }
                    }
                };
                xhr.send(`recipe_id=${recipeId}&day=${day}&meal_type=${mealType}&week=${<?= $current_week ?>}&year=${<?= $current_year ?>}`);
            } else {
                console.warn("No se seleccionó ningún elemento en el datalist.");
            }
        }

        function deleteRecipe(menuPlannerId, element) {
            if (confirm('Are you sure you want to delete this recipe?')) {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "delete_recipe_from_menu.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200 && xhr.responseText.includes("successfully")) {
                            const li = element.closest('li');
                            li.remove();
                        } else {
                            console.error("Error al eliminar la receta:", xhr.responseText);
                        }
                    }
                };
                xhr.send(`menu_planner_id=${menuPlannerId}`);
            }
        }

        function assignRecipeId(inputElement) {
            const datalist = inputElement.getAttribute('list');
            const option = document.querySelector(`#${datalist} option[value="${inputElement.value}"]`);
            if (option) {
                inputElement.setAttribute("data-id", option.getAttribute('data-id'));
            } else {
                inputElement.removeAttribute("data-id");
            }
        }
    </script>
</head>
<body class="container mt-5">
<h1 class="text-center">🍽️ Welcome to your Menu Planner</h1>
<br>

<!-- Navegación entre semanas -->
<div class="text-center mb-4">
    <a href="home.php?week=<?= $current_week - 1 ?>" class="btn btn-info btn-sm">Previous Week</a>
    <span>Week <?= $current_week ?></span>
    <a href="home.php?week=<?= $current_week + 1 ?>" class="btn btn-info btn-sm">Next Week</a>
</div>

<h3>Weekly Menu Summary - Week <?= $current_week ?></h3>
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
                            <?php if (isset($menu_data[$day][$meal])): ?>
                                <ul class="list-unstyled mb-0 recipe-list">
                                    <?php foreach ($menu_data[$day][$meal] as $recipe): ?>
                                        
                                        <li>
                                        <div class="card-body">
                                            <a href="recipe_details.php?id=<?= $recipe['recipe_id'] ?>">- <?= htmlspecialchars($recipe['recipe_name']) ?></a>
                                            <span class="delete-btn" onclick="deleteRecipe(<?= $recipe['menu_planner_id'] ?>, this)">❌</span>
                                    </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <ul class="list-unstyled mb-0 recipe-list">
                                    <li class="text-muted"></li>
                                </ul>
                            <?php endif; ?>
                            <div class="datalist-container">
                                <input list="recipes-<?= $meal ?>-list" class="form-control" oninput="assignRecipeId(this)" onchange="addRecipe('<?= $day ?>', '<?= $meal ?>', this)">
                                <datalist id="recipes-<?= $meal ?>-list">
                                    <?php foreach ($categories as $category): ?>
                                        <?php if (isset($recipes_by_category[$category])): ?>
                                            <?php foreach ($recipes_by_category[$category] as $recipe): ?>
                                                <option value="<?= htmlspecialchars($recipe['name']) ?>" data-id="<?= $recipe['id'] ?>"></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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

<div class="btn-group mt-4">
    <a href="list_recipes.php" class="btn btn-primary">📚 Recipe Library</a>

    <!-- Botón para generar la lista de la compra -->
    <form action="generate_shopping_list.php" method="POST" style="display: inline-block;">
        <input type="hidden" name="week" value="<?= $current_week ?>">
        <button type="submit" class="btn btn-info">🛒 Generate Shopping List</button>
    </form>
</div>



<p class="mt-3 text-center">
    <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
</p>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
