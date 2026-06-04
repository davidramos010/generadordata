<?php
/**
 * api_proxy.php
 * Proxy PHP para intermediar entre el frontend y la API Laravel.
 * Ejecuta login + peticiones de generacion/validacion y devuelve resultados normalizados.
 */

// === Configuracion parametrizada ===
$API_BASE_URL = getenv('GD_API_BASE_URL') ?: 'http://localhost:8001';
$LOGIN_EMAIL = 'email@test.com';
$LOGIN_PASS = 'psw';

// Ajustar estos endpoints si Swagger usa otra convencion.
$DOCUMENT_ENDPOINTS = [
    'DNI' => ['method' => 'GET', 'endpoint' => 'api/generate-dni'],
    'NIF' => ['method' => 'GET', 'endpoint' => 'api/generate-nif'],
    'NIE' => ['method' => 'GET', 'endpoint' => 'api/generate-nie'],
    'CIF' => ['method' => 'GET', 'endpoint' => 'api/generate-cif-by-type'],
];
$CUPS_ENDPOINT = ['method' => 'POST', 'endpoint' => 'api/cups/generate'];
$BANK_ENDPOINTS = [
    'IBAN' => ['method' => 'GET', 'endpoint' => 'api/generate-iban'],
    'VALIDAR_IBAN' => ['method' => 'GET', 'endpoint' => 'api/validate-iban'],
    'TC' => ['method' => 'GET', 'endpoint' => 'api/generate-tarjeta'],
    'VALIDAR_TC' => ['method' => 'LOCAL', 'endpoint' => 'validate-tc'],
];

// === Headers de respuesta ===
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['tipo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro "tipo" es requerido.']);
    exit;
}

$categoria = isset($input['categoria']) ? strtolower(trim($input['categoria'])) : 'documentos';
$tipo = strtoupper(trim($input['tipo']));
$payload = isset($input['data']) && is_array($input['data']) ? $input['data'] : [];
$cantidad = isset($input['cantidad']) ? intval($input['cantidad']) : 1;
$tipoCif = isset($input['tipoCif']) ? strtoupper(trim($input['tipoCif'])) : 'B';

function failRequest($message, $status = 400, $details = null)
{
    http_response_code($status);
    $response = ['error' => $message];
    if ($details !== null) {
        $response['details'] = $details;
    }
    echo json_encode($response);
    exit;
}

function normalizeBaseUrl($baseUrl)
{
    return rtrim($baseUrl, '/');
}

function validateCantidad($cantidad)
{
    if ($cantidad < 1 || $cantidad > 20) {
        failRequest('Cantidad debe estar entre 1 y 20.');
    }
}

/**
 * Ejecuta el login en la API y devuelve el token.
 */
function apiLogin($baseUrl, $email, $password)
{
    $url = normalizeBaseUrl($baseUrl) . '/api/login';

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

    if ($httpCode === 200 && is_array($data) && isset($data['token'])) {
        return ['token' => $data['token']];
    }

    return ['error' => 'HTTP ' . $httpCode . ' - ' . $response];
}

/**
 * Ejecuta una peticion autenticada contra la API.
 */
function apiRequest($baseUrl, $token, $method, $endpoint, $params = [], $body = [])
{
    $url = normalizeBaseUrl($baseUrl) . '/' . ltrim($endpoint, '/');
    $method = strtoupper($method);

    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    } else {
        $options[CURLOPT_HTTPGET] = true;
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'Error de conexion: ' . $error, 'httpCode' => 0];
    }

    $data = json_decode($response, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        $data = ['raw' => $response];
    }
    if (!is_array($data)) {
        $data = ['data' => $data];
    }

    $data['httpCode'] = $httpCode;
    return $data;
}

function buildDocumentRequest($tipo, $cantidad, $tipoCif, $documentEndpoints)
{
    $tiposValidos = array_keys($documentEndpoints);
    if (!in_array($tipo, $tiposValidos, true)) {
        failRequest('Tipo invalido. Valores validos: DNI, NIF, NIE, CIF.');
    }

    validateCantidad($cantidad);

    $params = ['result' => $cantidad];
    if ($tipo === 'CIF') {
        $params['type'] = $tipoCif ?: 'B';
    }

    return [
        'method' => $documentEndpoints[$tipo]['method'],
        'endpoint' => $documentEndpoints[$tipo]['endpoint'],
        'params' => $params,
        'body' => [],
    ];
}

function buildCupsRequest($payload, $cupsEndpoint)
{
    $tipo = isset($payload['tipo']) ? trim($payload['tipo']) : '';
    $distribuidora = isset($payload['distribuidora']) ? trim($payload['distribuidora']) : '';
    $cantidad = isset($payload['cantidad']) ? intval($payload['cantidad']) : 1;
    $incluirSufijo = isset($payload['incluirSufijo']) ? filter_var($payload['incluirSufijo'], FILTER_VALIDATE_BOOLEAN) : false;

    validateCantidad($cantidad);

    if ($tipo === '' || $distribuidora === '') {
        failRequest('CUPS requiere tipo, distribuidora, cantidad y sufijo.');
    }

    return [
        'method' => $cupsEndpoint['method'],
        'endpoint' => $cupsEndpoint['endpoint'],
        'params' => [],
        'body' => [
            'tipo' => $tipo,
            'distribuidora' => $distribuidora,
            'cantidad' => $cantidad,
            'incluirSufijo' => $incluirSufijo,
        ],
    ];
}

function buildBankRequest($tipo, $payload, $bankEndpoints)
{
    if (!isset($bankEndpoints[$tipo])) {
        failRequest('Tipo bancario invalido. Valores validos: IBAN, VALIDAR_IBAN, TC, VALIDAR_TC.');
    }

    $body = [];

    if ($tipo === 'VALIDAR_IBAN') {
        $iban = isset($payload['iban']) ? strtoupper(trim($payload['iban'])) : '';
        if ($iban === '') {
            failRequest('Validar IBAN requiere el campo iban.');
        }
        $params['iban'] = $iban;
    }

    if ($tipo === 'TC') {
        $cardType = isset($payload['type']) ? strtoupper(trim($payload['type'])) : '';
        if (!in_array($cardType, ['VISA', 'MASTERCARD', 'AMEX'], true)) {
            failRequest('Tipo TC invalido. Valores validos: VISA, MASTERCARD, AMEX.');
        }
        $params['type'] = $cardType;
    }

    if ($tipo === 'VALIDAR_TC') {
        $cardType = isset($payload['tipo']) ? strtoupper(trim($payload['tipo'])) : '';
        $numero = isset($payload['numero']) ? trim($payload['numero']) : '';
        if (!in_array($cardType, ['VISA', 'MASTERCARD', 'AMEX'], true)) {
            failRequest('Tipo TC invalido. Valores validos: VISA, MASTERCARD, AMEX.');
        }
        if ($numero === '') {
            failRequest('Validar TC requiere el campo numero.');
        }
        $body['tipo'] = $cardType;
        $body['numero'] = $numero;
    }

    return [
        'method' => $bankEndpoints[$tipo]['method'],
        'endpoint' => $bankEndpoints[$tipo]['endpoint'],
        'params' => isset($params) ? $params : [],
        'body' => $body,
    ];
}

function normalizeCardNumber($number)
{
    return preg_replace('/\D+/', '', $number);
}

function matchesCardType($number, $type)
{
    if ($type === 'VISA') {
        return preg_match('/^4\d{12}(\d{3})?(\d{3})?$/', $number) === 1;
    }
    if ($type === 'MASTERCARD') {
        return preg_match('/^(5[1-5]\d{14}|2(2[2-9][1-9]|[3-6]\d{2}|7[01]\d|720)\d{12})$/', $number) === 1;
    }
    if ($type === 'AMEX') {
        return preg_match('/^3[47]\d{13}$/', $number) === 1;
    }
    return false;
}

function passesLuhn($number)
{
    $sum = 0;
    $double = false;

    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $digit = intval($number[$i]);
        if ($double) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
        $double = !$double;
    }

    return $sum % 10 === 0;
}

function validateCardLocally($body)
{
    $number = normalizeCardNumber($body['numero']);
    $type = strtoupper($body['tipo']);
    $valid = $number !== '' && matchesCardType($number, $type) && passesLuhn($number);

    return [
        'data' => [
            'tipo' => $type,
            'numero' => $number,
            'valido' => $valid,
        ],
    ];
}

switch ($categoria) {
    case 'documentos':
        $requestConfig = buildDocumentRequest($tipo, $cantidad, $tipoCif, $DOCUMENT_ENDPOINTS);
        break;
    case 'cups':
        $requestConfig = buildCupsRequest($payload, $CUPS_ENDPOINT);
        break;
    case 'bancos':
        $requestConfig = buildBankRequest($tipo, $payload, $BANK_ENDPOINTS);
        break;
    default:
        failRequest('Categoria invalida. Valores validos: documentos, cups, bancos.');
}

if ($requestConfig['method'] === 'LOCAL' && $requestConfig['endpoint'] === 'validate-tc') {
    echo json_encode(validateCardLocally($requestConfig['body']));
    exit;
}

$loginResult = apiLogin($API_BASE_URL, $LOGIN_EMAIL, $LOGIN_PASS);

if (isset($loginResult['error'])) {
    failRequest('Login fallido: ' . $loginResult['error'], 500);
}

$result = apiRequest(
    $API_BASE_URL,
    $loginResult['token'],
    $requestConfig['method'],
    $requestConfig['endpoint'],
    $requestConfig['params'],
    $requestConfig['body']
);

$httpCode = isset($result['httpCode']) ? intval($result['httpCode']) : 0;
unset($result['httpCode']);

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code($httpCode ?: 500);
    echo json_encode([
        'error' => isset($result['message']) ? $result['message'] : 'Error al ejecutar la peticion.',
        'details' => $result,
    ]);
    exit;
}

if (array_key_exists('data', $result) && count($result) === 1) {
    echo json_encode(['data' => $result['data']]);
    exit;
}

echo json_encode(['data' => $result]);
