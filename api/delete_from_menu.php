<?php
require_once __DIR__ . '/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menu_planner_id = (int)$_POST['menu_planner_id'];

    $stmt = $conn->prepare("DELETE FROM menu_planner WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $menu_planner_id, $_SESSION['user_id']);
    echo $stmt->execute() ? "Recipe removed successfully" : "Error removing recipe";
}
