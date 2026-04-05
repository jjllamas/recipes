<?php
require_once __DIR__ . '/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $week = (int)$_POST['week'];

    $stmt = $conn->prepare("
        SELECT i.name AS ingredient_name, ri.quantity, ri.unit
        FROM menu_planner mp
        JOIN recipe_ingredients ri ON mp.recipe_id = ri.recipe_id
        JOIN ingredients i ON ri.ingredient_id = i.id
        WHERE mp.week = ? AND mp.user_id = ?
        ORDER BY i.name");
    $stmt->bind_param("ii", $week, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $shopping_list = [];
    while ($row = $result->fetch_assoc()) {
        $key = $row['ingredient_name'] . '|' . $row['unit'];
        if (!isset($shopping_list[$key])) {
            $shopping_list[$key] = ['name' => $row['ingredient_name'], 'quantity' => 0, 'unit' => $row['unit']];
        }
        $shopping_list[$key]['quantity'] += $row['quantity'];
    }
    $stmt->close();
}

$pageTitle = 'Shopping List';
include __DIR__ . '/../includes/header.php';
?>

<h2 class="text-center">🛒 Shopping List<?= isset($week) ? ' — Week ' . $week : '' ?></h2>
<div class="card mt-4">
    <div class="card-body">
        <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
            <p class="text-center text-muted">Submit from the Menu Planner to generate a shopping list.</p>
        <?php elseif (!empty($shopping_list)): ?>
            <ul class="list-group">
                <?php foreach ($shopping_list as $details): ?>
                    <li class="list-group-item">
                        <strong><?= htmlspecialchars($details['name']) ?>:</strong>
                        <?= $details['quantity'] ?>
                        <?= htmlspecialchars($details['unit']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-center text-muted">No ingredients found for the selected week.</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
