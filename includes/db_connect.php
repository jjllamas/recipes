<?php

$localConfig = __DIR__ . '/db_config.local.php';

if (!is_readable($localConfig)) {
    header('HTTP/1.1 503 Service Unavailable');
    exit(
        'Falta la configuración local de la base de datos. ' .
        'Copia includes/db_config.example.php a includes/db_config.local.php y define host, usuario, contraseña y nombre de la base de datos.'
    );
}

$config = require $localConfig;

$conn = new mysqli(
    $config['host'],
    $config['user'],
    $config['password'],
    $config['database']
);

if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

$charset = $config['charset'] ?? 'utf8mb4';
$conn->set_charset($charset);
