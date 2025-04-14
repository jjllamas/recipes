<?php

// Habilitar la visualización de errores para depuración
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

ini_set('session.gc_maxlifetime', 10800); // 3 horas
ini_set('session.cookie_lifetime', 10800); // 3 horas

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
