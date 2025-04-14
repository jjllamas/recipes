<?php
include 'includes/db_connect.php';
include 'web_session_start.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $week = (int)$_POST['week'];
    $user_id = $_SESSION['user_id'];

    // Consultar los ingredientes de todas las recetas del menú semanal seleccionado
    $query = "
        SELECT i.name AS ingredient_name, ri.quantity, ri.unit
        FROM menu_planner mp
        JOIN recipe_ingredients ri ON mp.recipe_id = ri.recipe_id
        JOIN ingredients i ON ri.ingredient_id = i.id
        WHERE mp.week = ? AND mp.user_id = ?
        ORDER BY i.name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $week, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Consolidar la lista de la compra
    $shopping_list = [];
    while ($row = $result->fetch_assoc()) {
        $ingredient_name = $row['ingredient_name'];
        $quantity = $row['quantity'];
        $unit = $row['unit'];

        if (!isset($shopping_list[$ingredient_name])) {
            $shopping_list[$ingredient_name] = ['quantity' => 0, 'unit' => $unit];
        }
        $shopping_list[$ingredient_name]['quantity'] += $quantity;
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shopping List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2 class="text-center">🛒 Shopping List for Week <?= htmlspecialchars($week) ?></h2>
    <div class="card mt-4">
        <div class="card-body">
            <?php if (!empty($shopping_list)): ?>
                <ul class="list-group">
                    <?php foreach ($shopping_list as $ingredient => $details): ?>
                        <li class="list-group-item">
                            <strong><?= htmlspecialchars($ingredient) ?>:</strong> 
                            <?= htmlspecialchars($details['quantity']) ?> 
                            <?= htmlspecialchars($details['unit']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-center text-muted">No ingredients found for the selected week.</p>
            <?php endif; ?>
        </div>
    </div>

    <p class="mt-3 text-center">
        <a href="home.php" class="btn btn-secondary btn-block">Back to Menu Planner</a>
    </p>
</body>
</html>
