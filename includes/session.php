<?php
require_once __DIR__ . '/config.php';

ini_set('session.gc_maxlifetime', 10800);
ini_set('session.cookie_lifetime', 10800);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

require_once __DIR__ . '/db_connect.php';
