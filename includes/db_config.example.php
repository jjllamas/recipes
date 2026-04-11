<?php

/**
 * Plantilla de configuración de base de datos.
 * Copia este archivo a db_config.local.php y ajusta los valores para tu entorno local (Laragon).
 */
return [
    'host'              => 'localhost',
    'user'              => 'root',
    'password'          => '',
    'database'          => 'recipes',
    'charset'           => 'utf8mb4',
    'anthropic_api_key' => '',   // https://console.anthropic.com/
];
