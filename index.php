<?php
require_once __DIR__ . '/includes/config.php';
session_start();
header('Location: ' . BASE_URL . (isset($_SESSION['user_id']) ? '/menu/' : '/auth/login.php'));
exit();
