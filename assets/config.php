<?php
/**
 * config.php
 * Configuración centralizada de credenciales y rutas (API Laravel + MySQL).
 * Prioridad: assets/config.local.php (no versionado, para producción),
 * después la variable de entorno del mismo nombre (docker-compose.yml)
 * y por último el valor por defecto de aquí.
 */

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// === API Laravel (usado por api_proxy.php) ===
defined('API_BASE_URL') || define('API_BASE_URL', getenv('API_BASE_URL') ?: 'https://api.generadordata.com/api');
defined('LOGIN_EMAIL')  || define('LOGIN_EMAIL',  getenv('LOGIN_EMAIL')  ?: 'api@generadordata.com');
defined('LOGIN_PASS')   || define('LOGIN_PASS',   getenv('LOGIN_PASS')   ?: 'generadordata');

// === Base de datos MySQL (usado por counter.php) ===
defined('DB_HOST')     || define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
defined('DB_USERNAME') || define('DB_USERNAME', getenv('DB_USERNAME') ?: 'userBD');
defined('DB_PASSWORD') || define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'passBD');
defined('DB_NAME')     || define('DB_NAME',     getenv('DB_NAME')     ?: 'admin_BD');
