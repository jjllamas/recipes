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

define('ANTHROPIC_API_KEY', $config['anthropic_api_key'] ?? '');

// Auto-migrate: add prep_ahead column if missing
$_col = $conn->query("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='recipes' AND column_name='prep_ahead'");
if ($_col && $_col->fetch_assoc()['c'] == 0) {
    $conn->query("ALTER TABLE recipes ADD COLUMN prep_ahead TEXT NULL");
}
unset($_col);
