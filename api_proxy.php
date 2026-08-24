<?php
/**
 * api_proxy.php
 * Proxy PHP para intermediar entre el frontend y la API Laravel.
 * Ejecuta login + reenvía la acción solicitada (whitelist) y devuelve el resultado.
 */

require_once __DIR__ . '/assets/config.php';
$API_BASE_URL = API_BASE_URL;
$LOGIN_EMAIL = LOGIN_EMAIL;
$LOGIN_PASS = LOGIN_PASS;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use POST.']);
    exit;
}

// === Whitelist de acciones permitidas ===
// method: GET|POST, path: ruta relativa a la API, params: parámetros aceptados
$ACTIONS = [
    'generate-dni'          => ['method' => 'GET', 'path' => 'generate-dni', 'params' => ['result']],
    'generate-nif'          => ['method' => 'GET', 'path' => 'generate-nif', 'params' => ['result']],
    'generate-nie'          => ['method' => 'GET', 'path' => 'generate-nie', 'params' => ['result']],
    'generate-ssn'          => ['method' => 'GET', 'path' => 'generate-ssn', 'params' => ['result']],
    'generate-cif'          => ['method' => 'GET', 'path' => 'generate-cif', 'params' => ['result']],
    'generate-cif-by-type'  => ['method' => 'GET', 'path' => 'generate-cif-by-type', 'params' => ['result', 'type']],
    'validate-document'     => ['method' => 'GET', 'path' => 'validate-document', 'params' => ['document', 'type']],

    'generate-iban'         => ['method' => 'GET', 'path' => 'generate-iban', 'params' => []],
    'validate-iban'         => ['method' => 'GET', 'path' => 'validate-iban', 'params' => ['iban']],
    'generate-cuenta'       => ['method' => 'GET', 'path' => 'generate-cuenta', 'params' => []],
    'generate-tarjeta'      => ['method' => 'GET', 'path' => 'generate-tarjeta', 'params' => ['type']],

    'cups-generate'         => ['method' => 'POST', 'path' => 'cups/generate', 'params' => ['tipo', 'distribuidora', 'cantidad', 'incluirSufijo']],
    'cups-validate'         => ['method' => 'POST', 'path' => 'cups/validate', 'params' => ['cups']],
];

// === Leer body JSON ===
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro "action" es requerido.']);
    exit;
}

$action = $input['action'];
$rawParams = isset($input['params']) && is_array($input['params']) ? $input['params'] : [];

if (!isset($ACTIONS[$action])) {
    http_response_code(400);
    echo json_encode(['error' => 'Acción no soportada: ' . $action]);
    exit;
}

$actionConfig = $ACTIONS[$action];

// Filtrar solo los parámetros permitidos para esta acción, descartando vacíos/null
$params = [];
foreach ($actionConfig['params'] as $key) {
    if (isset($rawParams[$key]) && $rawParams[$key] !== '' && $rawParams[$key] !== null) {
        $params[$key] = $rawParams[$key];
    }
}

/**
 * Ejecuta el login en la API y devuelve el token.
 */
function apiLogin($baseUrl, $email, $password)
{
    $url = $baseUrl . '/login';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'email' => $email,
            'password' => $password,
        ]),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => 'curl_error: ' . $curlError];
    }

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['token'])) {
        return ['token' => $data['token']];
    }

    return ['error' => 'HTTP ' . $httpCode . ' — ' . $response];
}

/**
 * Ejecuta la petición a la API (GET o POST) según la acción y devuelve la respuesta decodificada.
 */
function apiCall($baseUrl, $token, $method, $endpoint, $params = [])
{
    $url = $baseUrl . '/' . $endpoint;

    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ];

    if ($method === 'POST') {
        $curlOpts[CURLOPT_POST] = true;
        $curlOpts[CURLOPT_POSTFIELDS] = json_encode($params);
    } else {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        $curlOpts[CURLOPT_HTTPGET] = true;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $curlOpts);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'Error de conexión: ' . $error, 'httpCode' => 0];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        $data = ['value' => $data];
    }
    $data['httpCode'] = $httpCode;

    return $data;
}

// === Flujo principal ===

// 1. Login
$loginResult = apiLogin($API_BASE_URL, $LOGIN_EMAIL, $LOGIN_PASS);

if (isset($loginResult['error'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Login fallido: ' . $loginResult['error']]);
    exit;
}

$token = $loginResult['token'];

// 2. Ejecutar acción
$result = apiCall($API_BASE_URL, $token, $actionConfig['method'], $actionConfig['path'], $params);

$httpCode = isset($result['httpCode']) ? $result['httpCode'] : 0;
unset($result['httpCode']);

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code($httpCode ?: 500);
    echo json_encode([
        'error' => isset($result['message']) ? $result['message'] : 'Error al ejecutar la acción.',
        'details' => $result,
    ]);
    exit;
}

// 3. Devolver datos (desenvolver 'value' si la respuesta original no era un objeto)
if (isset($result['value']) && count($result) === 1) {
    $result = $result['value'];
}

echo json_encode(['data' => $result]);
