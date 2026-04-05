<?php
require_once __DIR__ . '/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $day       = $_POST['day'];
    $recipe_id = (int)$_POST['recipe'];
    $user_id   = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO menu_planner (user_id, recipe_id, day) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $recipe_id, $day);

    if ($stmt->execute()) {
        header('Location: ' . BASE_URL . '/menu/planner.php');
        exit();
    } else {
        echo "Error assigning recipe: " . $conn->error;
    }
}
