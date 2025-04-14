<?php
include 'includes/db_connect.php';
include 'web_session_start.php';

// Habilitar la visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipe_id = $_POST['recipe_id'];
    $day = $_POST['day'];
    $meal_type = $_POST['meal_type'];
    $week = $_POST['week'];
    $year = $_POST['year'];
    $user_id = $_SESSION['user_id'];

    // Verificar si los valores llegan correctamente
    echo "Datos recibidos en el servidor:";
    var_dump($_POST);

    // Validar que se están enviando todos los campos necesarios
    if ($recipe_id && $day && $meal_type && $week && $year) {
        // Preparar la consulta para insertar en la base de datos
        $query = "INSERT INTO menu_planner (recipe_id, day, meal_type, week, year, user_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issiii", $recipe_id, $day, $meal_type, $week, $year, $user_id);

        // Ejecutar la consulta y manejar errores
        if ($stmt->execute()) {
            echo "Recipe added successfully";
        } else {
            echo "Error en la consulta SQL: " . $stmt->error;
        }
    } else {
        echo "Faltan campos requeridos.";
    }
}
?>
