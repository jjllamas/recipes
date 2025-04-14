<?php
include 'includes/db_connect.php';
include 'web_session_start.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $day = $_POST['day'];
    $recipe_id = $_POST['recipe'];
    $user_id = $_SESSION['user_id'];

    $query = "INSERT INTO menu_planner (user_id, recipe_id, day) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", $user_id, $recipe_id, $day);

    if ($stmt->execute()) {
        header("Location: menu_planner.php");
        exit();
    } else {
        echo "Error assigning recipe: " . $conn->error;
    }
}
?>
