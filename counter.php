<?php
/**
 * counter.php
 * Registra una visita por página en MySQL (tabla `conter`).
 * Lo llama assets/js/counter.js con POST {"page": "..."}.
 */

require_once __DIR__ . '/assets/config.php';

ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

const PAGINAS_PERMITIDAS = [
    'index', 'api', 'generador-iban', 'generador-tarjetas', 'generador-cups',
    'about', 'contact', 'rgpd', 'base64-pdf',
];

function responder($status, $body)
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responder(405, ['error' => 'Método no permitido. Use POST.']);
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if ($userAgent === '' || preg_match('/bot|crawl|spider|slurp|preview|lighthouse|headless|curl|wget|python|httpclient/i', $userAgent)) {
    responder(202, ['contado' => false]);
}

$input = json_decode(file_get_contents('php://input'), true);
$pagina = is_array($input) && isset($input['page']) ? $input['page'] : null;

if (!in_array($pagina, PAGINAS_PERMITIDAS, true)) {
    responder(400, ['error' => 'Página no válida.']);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    $db->set_charset('utf8mb4');

    $stmt = $db->prepare('INSERT INTO conter (page, num) VALUES (?, 1) ON DUPLICATE KEY UPDATE num = num + 1');
    $stmt->bind_param('s', $pagina);
    $stmt->execute();
    $stmt->close();
    $db->close();
} catch (mysqli_sql_exception $e) {
    error_log('counter.php: ' . $e->getMessage());
    responder(503, ['error' => 'Contador no disponible.']);
}

responder(200, ['contado' => true]);
