<?php
/**
 * jwtcontroller.php
 * Enrutador AJAX del decodificador/codificador JWT
 * (decodificador-jwt.html → aquí → assets/functions/jwt.php).
 * Acciones: decode, verify y encode. Nunca se registran tokens ni claves.
 */

define('GD_APP', true);
require_once __DIR__ . '/assets/functions/security.php';
require_once __DIR__ . '/assets/functions/jwt.php';

gd_json_headers();

$mensaje = function (array $r) {
    return gd_jwt_error_message($r['error']);
};

function campo(string $nombre): string
{
    $v = $_POST[$nombre] ?? '';
    return is_string($v) ? $v : '';
}

/** Material de verificación/firma: solo se acepta el tipo indicado en key_type. */
function material(string $tipo): array
{
    switch ($tipo) {
        case 'secret':
            return ['secret' => campo('secret'), 'secret_b64' => campo('secret_b64') === '1'];
        case 'public_key':
            return ['public_key' => campo('public_key')];
        case 'jwks':
            return ['jwks_uri' => campo('jwks_uri')];
        case 'private_key':
            return ['private_key' => campo('private_key')];
    }
    return [];
}

try {
    $bloqueo = gd_ajax_guard();
    if ($bloqueo !== null) {
        gd_respond($bloqueo, $mensaje);
    }

    if (!gd_rate_limit($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 60, 600, 'jwt')) {
        header('Retry-After: 600');
        gd_respond(gd_fail('LIMITE', 429), $mensaje);
    }

    switch ($_POST['action'] ?? '') {
        case 'decode':
            $r = gd_jwt_decode(campo('token'));
            break;
        case 'verify':
            $r = gd_jwt_verify(campo('token'), material(campo('key_type')));
            break;
        case 'encode':
            $r = gd_jwt_encode(campo('header'), campo('payload'), campo('alg'), material(campo('key_type')));
            break;
        default:
            $r = gd_fail('ACCION', 400);
    }

    if ($r['ok'] && isset($r['verify_error'])) {
        $r['verify_message'] = gd_jwt_error_message($r['verify_error']);
    }
    gd_respond($r, $mensaje);
} catch (Throwable $e) {
    error_log('jwtcontroller: ' . get_class($e) . ' en ' . $e->getFile() . ':' . $e->getLine());
    gd_respond(gd_fail('SERVIDOR', 500), $mensaje);
}
