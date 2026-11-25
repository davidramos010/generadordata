<?php
/**
 * base64controller.php
 * Enrutador AJAX del conversor Base64 ↔ PDF (base64-pdf.html → aquí → assets/functions/base64_pdf.php).
 * Acciones: decode (POST base64) y encode (POST multipart con el archivo "pdf").
 */

define('GD_APP', true);
require_once __DIR__ . '/assets/functions/security.php';
require_once __DIR__ . '/assets/functions/base64_pdf.php';

gd_json_headers();

$mensaje = function (array $r) {
    $code = $r['error'] === 'DEMASIADO_GRANDE' ? 'PDF_DEMASIADO_GRANDE' : $r['error'];
    return gd_b64_error_message($code, $r['parece'] ?? '');
};

try {
    $bloqueo = gd_ajax_guard();
    if ($bloqueo !== null) {
        if ($bloqueo['error'] === 'DEMASIADO_GRANDE') {
            $bloqueo['error'] = 'PDF_DEMASIADO_GRANDE';
        }
        gd_respond($bloqueo, $mensaje);
    }

    if (!gd_rate_limit($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 20, 600, 'base64')) {
        header('Retry-After: 600');
        gd_respond(gd_fail('LIMITE', 429), $mensaje);
    }

    switch ($_POST['action'] ?? '') {
        case 'decode':
            $texto = $_POST['base64'] ?? '';
            gd_respond(gd_b64_to_pdf(is_string($texto) ? $texto : ''), $mensaje);
            break;
        case 'encode':
            gd_respond(gd_pdf_to_b64($_FILES['pdf'] ?? []), $mensaje);
            break;
        default:
            gd_respond(gd_fail('ACCION', 400), $mensaje);
    }
} catch (Throwable $e) {
    error_log('base64controller: ' . get_class($e) . ' en ' . $e->getFile() . ':' . $e->getLine());
    gd_respond(gd_fail('SERVIDOR', 500), $mensaje);
}
