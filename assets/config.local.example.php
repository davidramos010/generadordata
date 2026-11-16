<?php
/**
 * Copia este archivo como assets/config.local.php en el servidor de producción
 * y rellena las credenciales reales. config.local.php está en .gitignore.
 * Solo hace falta definir las constantes que cambian respecto a config.php.
 */

define('DB_HOST',     'localhost');
define('DB_USERNAME', 'usuario_real');
define('DB_PASSWORD', 'contraseña_real');
define('DB_NAME',     'nombre_bd_real');
