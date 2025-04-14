<?php
include 'includes/db_connect.php';
include 'web_session_start.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menu_planner_id = $_POST['menu_planner_id'];

    // Asegurarse de que el usuario es el propietario del menú que intenta eliminar
    $delete_query = "DELETE FROM menu_planner WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("ii", $menu_planner_id, $_SESSION['user_id']);

    if ($stmt->execute()) {
        echo "Recipe removed successfully";
    } else {
        echo "Error removing recipe";
    }
}
?>



