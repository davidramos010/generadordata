<?php
/**
 * config.php
 * Configuración centralizada de credenciales y rutas (API Laravel + MySQL).
 * Cada constante usa la variable de entorno del mismo nombre si existe
 * (definida en docker-compose.yml), y si no, el valor por defecto de aquí.
 */

// === API Laravel (usado por api_proxy.php) ===
define('API_BASE_URL', getenv('API_BASE_URL') ?: 'http://172.20.0.1:8001/api');
define('LOGIN_EMAIL',  getenv('LOGIN_EMAIL')  ?: 'test@example.com');
define('LOGIN_PASS',   getenv('LOGIN_PASS')   ?: 'password');

// === Base de datos MySQL (usado por counter.php) ===
define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'userBD');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'passBD');
define('DB_NAME',     getenv('DB_NAME')     ?: 'admin_BD');
