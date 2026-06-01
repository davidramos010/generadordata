<?php
/**
 * api_proxy.php
 * Proxy PHP para intermediar entre el frontend y la API Laravel.
 * Ejecuta login + generación de documentos y devuelve los resultados.
 */

// === Configuración parametrizada ===
$API_BASE_URL = 'http://172.20.0.1:8001/api';
$LOGIN_EMAIL = 'test@example.com';
$LOGIN_PASS = 'password';

// === Headers de respuesta ===
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use POST.']);
    exit;
}

// === Leer parámetros del body JSON ===
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['tipo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro "tipo" es requerido.']);
    exit;
}

$tipo = strtoupper(trim($input['tipo']));
$cantidad = isset($input['cantidad']) ? intval($input['cantidad']) : 1;
$tipoCif = isset($input['tipoCif']) ? strtoupper(trim($input['tipoCif'])) : 'B';

// Validar tipo
$tiposValidos = ['DNI', 'NIF', 'NIE', 'CIF'];
if (!in_array($tipo, $tiposValidos)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo inválido. Valores válidos: DNI, NIF, NIE, CIF.']);
    exit;
}

// Validar cantidad
if ($cantidad < 1 || $cantidad > 20) {
    http_response_code(400);
    echo json_encode(['error' => 'Cantidad debe estar entre 1 y 20.']);
    exit;
}

/**
 * Ejecuta el login en la API y devuelve el token.
 *
 * @param string $baseUrl URL base de la API
 * @param string $email   Email del usuario
 * @param string $password Contraseña del usuario
 * @return string|null Token de autenticación o null si falla
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
 * Ejecuta la petición de generación a la API.
 *
 * @param string $baseUrl  URL base de la API
 * @param string $token    Bearer token
 * @param string $endpoint Ruta del endpoint (e.g. 'generate-dni')
 * @param array  $params   Parámetros query string
 * @return array Respuesta decodificada
 */
function apiGenerate($baseUrl, $token, $endpoint, $params = [])
{
    $url = $baseUrl . '/' . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'Error de conexión: ' . $error, 'httpCode' => 0];
    }

    $data = json_decode($response, true);
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

// 2. Construir endpoint y parámetros
$endpointMap = [
    'DNI' => 'generate-dni',
    'NIF' => 'generate-nif',
    'NIE' => 'generate-nie',
    'CIF' => 'generate-cif-by-type',
];

$endpoint = $endpointMap[$tipo];
$params = ['result' => $cantidad];

if ($tipo === 'CIF') {
    $params['type'] = $tipoCif;
}

// 3. Generar documentos
$result = apiGenerate($API_BASE_URL, $token, $endpoint, $params);

$httpCode = isset($result['httpCode']) ? $result['httpCode'] : 0;
unset($result['httpCode']);

if ($httpCode !== 200) {
    http_response_code($httpCode ?: 500);
    echo json_encode([
        'error' => isset($result['message']) ? $result['message'] : 'Error al generar documentos.',
        'details' => $result,
    ]);
    exit;
}

// 4. Devolver datos
echo json_encode(['data' => $result]);
