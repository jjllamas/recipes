<?php
include 'includes/db_connect.php';
include 'web_session_start.php';

// Obtener la semana seleccionada (por defecto la semana actual)
$selected_week = isset($_GET['week']) ? (int)$_GET['week'] : date('W');
$current_year = date('Y');

// Días de la semana
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Tipos de comida y colores
$meal_types = [
    'Breakfast' => 'bg-primary text-white',
    'Second Breakfast' => 'bg-secondary text-white',
    'Lunch' => 'bg-success text-white',
    'Afternoon Snack' => 'bg-warning text-dark',
    'Dinner' => 'bg-danger text-white'
];

// Consultar todas las recetas para el selector de recetas
$recipes_query = "SELECT id, name FROM recipes WHERE user_id = ?";
$stmt = $conn->prepare($recipes_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recipes_result = $stmt->get_result();
$recipes = [];
while ($row = $recipes_result->fetch_assoc()) {
    $recipes[] = $row;
}
$stmt->close();

// Consultar el menú semanal almacenado para la semana seleccionada
$menu_query = "
    SELECT mp.*, r.name as recipe_name 
    FROM menu_planner mp 
    JOIN recipes r ON mp.recipe_id = r.id 
    WHERE mp.week = ? AND mp.year = ? AND mp.user_id = ?";
$menu_stmt = $conn->prepare($menu_query);
$menu_stmt->bind_param("iii", $selected_week, $current_year, $_SESSION['user_id']);
$menu_stmt->execute();
$menu_result = $menu_stmt->get_result();

$menu_data = [];
while ($row = $menu_result->fetch_assoc()) {
    $menu_data[$row['day']][$row['meal_type']][] = [
        'recipe_name' => $row['recipe_name'],
        'id' => $row['id']
    ];
}
$menu_stmt->close();

// Función para rellenar huecos con sugerencias aleatorias
if (isset($_POST['fill_gaps'])) {
    $conn->begin_transaction();
    try {
        foreach ($days_of_week as $day) {
            foreach ($meal_types as $meal_type => $color) {
                if (empty($menu_data[$day][$meal_type])) {
                    // Categorías de recetas según el tipo de comida
                    $category_map = [
                        'Breakfast' => ['Breakfast'],
                        'Second Breakfast' => ['Snack'],
                        'Lunch' => ['Main dish', 'Side Dish', 'Dessert'],
                        'Afternoon Snack' => ['Snack'],
                        'Dinner' => ['Main dish', 'Side Dish', 'Dessert']
                    ];

                    $categories = $category_map[$meal_type];

                    // Para Comida y Cena, añadir en orden: Main dish, Side Dish, Dessert
                    if ($meal_type == 'Lunch' || $meal_type == 'Dinner') {
                        foreach ($categories as $category) {
                            $suggestion_query = "
                                SELECT id 
                                FROM recipes 
                                WHERE category_id = (
                                    SELECT id FROM categories WHERE name = ?
                                ) 
                                AND user_id = ? 
                                ORDER BY RAND() 
                                LIMIT 1";
                            $suggestion_stmt = $conn->prepare($suggestion_query);
                            $suggestion_stmt->bind_param("si", $category, $_SESSION['user_id']);
                            $suggestion_stmt->execute();
                            $suggestion_result = $suggestion_stmt->get_result();
                            
                            // Verificar si se encontró alguna receta
                            if ($suggestion_result->num_rows > 0) {
                                $recipe_id = $suggestion_result->fetch_assoc()['id'];
                                $insert_query = "INSERT INTO menu_planner (user_id, week, year, day, meal_type, recipe_id) VALUES (?, ?, ?, ?, ?, ?)";
                                $insert_stmt = $conn->prepare($insert_query);
                                $insert_stmt->bind_param("iiissi", $_SESSION['user_id'], $selected_week, $current_year, $day, $meal_type, $recipe_id);
                                $insert_stmt->execute();
                            }
                        }
                    } else {
                        // Para otras comidas, añadir una receta por categoría
                        foreach ($categories as $category) {
                            $suggestion_query = "
                                SELECT id 
                                FROM recipes 
                                WHERE category_id = (
                                    SELECT id FROM categories WHERE name = ?
                                ) 
                                AND user_id = ? 
                                ORDER BY RAND() 
                                LIMIT 1";
                            $suggestion_stmt = $conn->prepare($suggestion_query);
                            $suggestion_stmt->bind_param("si", $category, $_SESSION['user_id']);
                            $suggestion_stmt->execute();
                            $suggestion_result = $suggestion_stmt->get_result();
                            if ($suggestion_result->num_rows > 0) {
                                $recipe_id = $suggestion_result->fetch_assoc()['id'];
                                $insert_query = "INSERT INTO menu_planner (user_id, week, year, day, meal_type, recipe_id) VALUES (?, ?, ?, ?, ?, ?)";
                                $insert_stmt = $conn->prepare($insert_query);
                                $insert_stmt->bind_param("iiissi", $_SESSION['user_id'], $selected_week, $current_year, $day, $meal_type, $recipe_id);
                                $insert_stmt->execute();
                            }
                        }
                    }
                }
            }
        }
        $conn->commit();
        header("Location: menu_planner.php?week=$selected_week");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: Could not fill gaps with suggestions. " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menu Planner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .day-header {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
            padding: 10px 0;
            border: 1px solid #dee2e6;
            cursor: pointer;
        }
        .meal-header {
            font-weight: bold;
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .meal-content {
            background-color: #ffffff;
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .meal-container {
            border: 1px solid #dee2e6;
            margin-bottom: 5px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .add-recipe-form {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-top: 8px;
        }
        .delete-button {
            margin-left: 5px;
        }
    </style>
    <script>
        // Función para verificar y asignar el recipe_id en el formulario
        function assignRecipeId(form) {
            const input = form.querySelector('input[name="recipe_name"]');
            const recipeIdInput = form.querySelector('input[name="recipe_id"]');
            const datalist = document.getElementById('recipe-list');
            const option = Array.from(datalist.options).find(option => option.value === input.value);
            
            if (option) {
                recipeIdInput.value = option.dataset.id;
            } else {
                alert('Please select a valid recipe from the list.');
                return false;
            }

            return true;
        }
    </script>
</head>
<body class="container mt-5">
    <h2 class="text-center">🗓️ Weekly Menu Planner</h2>

    <!-- Formulario para seleccionar la semana -->
    <form method="GET" class="mb-4">
        <div class="row justify-content-center">
            <div class="col-auto">
                <label for="week" class="form-label">Select Week:</label>
                <input type="number" name="week" id="week" class="form-control" min="1" max="52" value="<?= $selected_week ?>" required>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary mt-4">Go</button>
            </div>
        </div>
    </form>

    <!-- Acordeón del planificador de menú -->
    <div class="accordion" id="menuAccordion">
        <?php foreach ($days_of_week as $index => $day): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading<?= $index ?>">
                    <button class="accordion-button <?= $index == 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>" aria-expanded="<?= $index == 0 ? 'true' : 'false' ?>" aria-controls="collapse<?= $index ?>">
                        <?= $day ?>
                    </button>
                </h2>
                <div id="collapse<?= $index ?>" class="accordion-collapse collapse <?= $index == 0 ? 'show' : '' ?>" aria-labelledby="heading<?= $index ?>" data-bs-parent="#menuAccordion">
                    <div class="accordion-body">
                        <?php foreach ($meal_types as $meal => $color): ?>
                            <div class="meal-container mb-2">
                                <div class="meal-header <?= $color ?> d-flex justify-content-between">
                                    <span><?= $meal ?></span>
                                    <!-- Formulario para añadir receta -->
                                    <form action="add_recipe_to_menu.php" method="POST" class="add-recipe-form" onsubmit="return assignRecipeId(this)">
                                        <input list="recipe-list" name="recipe_name" class="form-control" placeholder="Search recipe..." required>
                                        <datalist id="recipe-list">
                                            <?php foreach ($recipes as $recipe): ?>
                                                <option value="<?= htmlspecialchars($recipe['name']) ?>" data-id="<?= $recipe['id'] ?>"></option>
                                            <?php endforeach; ?>
                                        </datalist>
                                        <input type="hidden" name="recipe_id">
                                        <input type="hidden" name="week" value="<?= $selected_week ?>">
                                        <input type="hidden" name="day" value="<?= $day ?>">
                                        <input type="hidden" name="meal_type" value="<?= $meal ?>">
                                        <button type="submit" class="btn btn-light text-dark">ADD</button>
                                    </form>
                                </div>
                                <div class="meal-content">
                                    <?php if (isset($menu_data[$day][$meal])): ?>
                                        <ul class="list-group mb-0">
                                            <?php foreach ($menu_data[$day][$meal] as $recipe): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?= htmlspecialchars($recipe['recipe_name']) ?>
                                                    <form action="delete_recipe_from_menu.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="menu_id" value="<?= $recipe['id'] ?>">
                                                        <input type="hidden" name="week" value="<?= $selected_week ?>"> <!-- Añadir la semana -->
                                                        <button type="submit" class="btn btn-sm btn-danger delete-button">Delete</button>
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

    <!-- Botón para rellenar huecos con sugerencias -->
    <div class="text-center mt-4">
        <form method="POST">
            <button type="submit" name="fill_gaps" class="btn btn-info">🔄 Fill Gaps with Suggestions</button>
        </form>
    </div>

    <!-- Botón para generar la lista de la compra -->
    <div class="text-center mt-4">
        <form action="generate_shopping_list.php" method="POST">
            <input type="hidden" name="week" value="<?= $selected_week ?>">
            <button type="submit" class="btn btn-info">Generate Shopping List</button>
        </form>
    </div>

    <p class="mt-3 text-center">
        <a href="home.php" class="btn btn-secondary btn-block">Back to Home</a>
    </p>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
