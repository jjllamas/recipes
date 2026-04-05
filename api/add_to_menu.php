<?php
require_once __DIR__ . '/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipe_id = (int)$_POST['recipe_id'];
    $day       = $_POST['day'];
    $meal_type = $_POST['meal_type'];
    $week      = (int)$_POST['week'];
    $year      = (int)$_POST['year'];
    $user_id   = $_SESSION['user_id'];

    if ($recipe_id && $day && $meal_type && $week && $year) {
        $stmt = $conn->prepare("INSERT INTO menu_planner (recipe_id, day, meal_type, week, year, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiii", $recipe_id, $day, $meal_type, $week, $year, $user_id);
        echo $stmt->execute() ? "Recipe added successfully" : "Error: " . $stmt->error;
    } else {
        echo "Missing required fields.";
    }
}
