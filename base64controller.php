<?php
/**
 * base64controller.php
 * Enrutador AJAX del conversor Base64 ↔ PDF (base64-pdf.html → aquí → assets/functions/base64_pdf.php).
 * Acciones: decode (POST base64) y encode (POST multipart con el archivo "pdf").
 */

define('GD_APP', true);
require_once __DIR__ . '/assets/functions/base64_pdf.php';

ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('Referrer-Policy: same-origin');

function responder(array $resultado)
{
    if ($resultado['ok']) {
        unset($resultado['status']);
        http_response_code(200);
    } else {
        http_response_code($resultado['status'] ?? 400);
        $resultado = [
            'ok'      => false,
            'error'   => $resultado['error'],
            'message' => gd_b64_error_message($resultado['error'], $resultado['parece'] ?? ''),
        ];
    }
    echo json_encode($resultado, JSON_UNESCAPED_SLASHES);
    exit;
}

function bytes_ini(string $valor): int
{
    $valor = trim($valor);
    $num = (int) $valor;
    switch (strtolower(substr($valor, -1))) {
        case 'g': return $num * 1024 * 1024 * 1024;
        case 'm': return $num * 1024 * 1024;
        case 'k': return $num * 1024;
        default:  return $num;
    }
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        responder(gd_fail('METODO', 405));
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '') !== $host) {
        responder(gd_fail('ORIGEN', 403));
    }
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
        responder(gd_fail('ORIGEN', 403));
    }

    $limitePost = bytes_ini((string) ini_get('post_max_size'));
    if ($limitePost > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limitePost) {
        responder(gd_fail('PDF_DEMASIADO_GRANDE', 413));
    }

    if (!gd_rate_limit($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')) {
        header('Retry-After: 600');
        responder(gd_fail('LIMITE', 429));
    }

    $accion = $_POST['action'] ?? '';
    switch ($accion) {
        case 'decode':
            $texto = $_POST['base64'] ?? '';
            responder(gd_b64_to_pdf(is_string($texto) ? $texto : ''));
            break;
        case 'encode':
            responder(gd_pdf_to_b64($_FILES['pdf'] ?? []));
            break;
        default:
            responder(gd_fail('ACCION', 400));
    }
} catch (Throwable $e) {
    error_log('base64controller: ' . get_class($e) . ' en ' . $e->getFile() . ':' . $e->getLine());
    responder(gd_fail('SERVIDOR', 500));
}
