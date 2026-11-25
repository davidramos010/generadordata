<?php
/**
 * jwt.php
 * Decodificar, verificar y firmar JWT (JWS compacto). Solo define funciones:
 * se carga desde jwtcontroller.php (junto con security.php) y no produce salida.
 * Tokens, secretos y claves se usan solo en memoria: nunca se guardan, registran
 * ni se incluyen en los mensajes de error.
 */

defined('GD_APP') || exit;

const GD_JWT_MAX_TOKEN = 64 * 1024;
const GD_JWT_MAX_KEY   = 16 * 1024;
const GD_JWT_MAX_JSON  = 32 * 1024;
const GD_JWT_JSON_DEPTH = 32;
const GD_JWKS_MAX_BYTES = 100 * 1024;
const GD_JWKS_MAX_KEYS  = 50;

// alg => [familia, hash, tamaño de firma ECDSA por coordenada | tamaño mínimo de secreto HMAC]
const GD_JWT_ALGS = [
    'HS256' => ['hmac', 'sha256', 32], 'HS384' => ['hmac', 'sha384', 48], 'HS512' => ['hmac', 'sha512', 64],
    'RS256' => ['rsa', OPENSSL_ALGO_SHA256, 0], 'RS384' => ['rsa', OPENSSL_ALGO_SHA384, 0], 'RS512' => ['rsa', OPENSSL_ALGO_SHA512, 0],
    'ES256' => ['ec', OPENSSL_ALGO_SHA256, 32], 'ES384' => ['ec', OPENSSL_ALGO_SHA384, 48], 'ES512' => ['ec', OPENSSL_ALGO_SHA512, 66],
    'PS256' => ['pss', null, 0], 'PS384' => ['pss', null, 0], 'PS512' => ['pss', null, 0],
    'EdDSA' => ['eddsa', null, 0],
    'none'  => ['none', null, 0],
];
const GD_EC_CURVES = ['ES256' => 'prime256v1', 'ES384' => 'secp384r1', 'ES512' => 'secp521r1'];

// ── Base64url ─────────────────────────────────────────────────

function gd_b64url_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function gd_b64url_decode(string $s): ?string
{
    if ($s === '' ) {
        return '';
    }
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $s) || strlen($s) % 4 === 1) {
        return null;
    }
    $bin = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    return $bin === false ? null : $bin;
}

function gd_json_object(string $json): ?array
{
    if (substr(ltrim($json), 0, 1) !== '{') {
        return null;
    }
    try {
        $data = json_decode($json, true, GD_JWT_JSON_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    } catch (JsonException $e) {
        return null;
    }
    return is_array($data) && ($data === [] || array_keys($data) !== range(0, count($data) - 1)) ? $data : null;
}

// ── Lectura del token ─────────────────────────────────────────

function gd_jwt_parse(string $token): array
{
    $token = trim($token);
    if ($token === '') {
        return gd_fail('JWT_VACIO', 400);
    }
    if (strlen($token) > GD_JWT_MAX_TOKEN) {
        return gd_fail('JWT_DEMASIADO_GRANDE', 413);
    }
    $token = preg_replace('/^Bearer\s+/i', '', $token);
    $token = preg_replace('/\s+/', '', $token);

    $partes = explode('.', $token);
    if (count($partes) === 5) {
        return gd_fail('JWT_JWE');
    }
    if (count($partes) !== 3) {
        return gd_fail('JWT_PARTES');
    }

    $headerJson = gd_b64url_decode($partes[0]);
    $payloadJson = gd_b64url_decode($partes[1]);
    $firma = gd_b64url_decode($partes[2]);
    if ($headerJson === null || $headerJson === '') {
        return gd_fail('JWT_HEADER_B64');
    }
    if ($payloadJson === null) {
        return gd_fail('JWT_PAYLOAD_B64');
    }
    if ($firma === null) {
        return gd_fail('JWT_FIRMA_B64');
    }

    $header = gd_json_object($headerJson);
    if ($header === null) {
        return gd_fail('JWT_HEADER_JSON');
    }
    $payload = gd_json_object($payloadJson);
    if ($payload === null) {
        return gd_fail('JWT_PAYLOAD_JSON');
    }

    $alg = $header['alg'] ?? null;
    if (!is_string($alg) || !isset(GD_JWT_ALGS[$alg])) {
        return gd_fail('JWT_ALG_DESCONOCIDO');
    }

    return [
        'ok'      => true,
        'header'  => $header,
        'payload' => $payload,
        'alg'     => $alg,
        'parts'   => $partes,
        'signing_input' => $partes[0] . '.' . $partes[1],
        'signature' => $firma,
    ];
}

// ── Claims ────────────────────────────────────────────────────

const GD_JWT_CLAIMS = [
    'iss' => ['Emisor', 'Quién emitió el token, normalmente el servidor de autorización.'],
    'sub' => ['Sujeto', 'A quién se refiere el token, normalmente el ID del usuario.'],
    'aud' => ['Audiencia', 'Destinatarios que deben aceptar el token (una o varias APIs o aplicaciones).'],
    'exp' => ['Caducidad', 'A partir de este momento el token deja de ser válido.'],
    'nbf' => ['No antes de', 'El token no es válido antes de este momento.'],
    'iat' => ['Emitido en', 'Momento en que se creó el token.'],
    'jti' => ['ID del token', 'Identificador único del token; sirve para evitar que se reutilice.'],
    'scope' => ['Alcance', 'Permisos concedidos al token.'],
    'azp' => ['Parte autorizada', 'Cliente al que se emitió el token (OpenID Connect).'],
    'nonce' => ['Nonce', 'Valor de un solo uso que liga el ID token a la petición de inicio de sesión.'],
    'auth_time' => ['Hora de autenticación', 'Cuándo se autenticó el usuario.'],
];

function gd_jwt_claims(array $payload, int $now): array
{
    $claims = [];
    foreach ($payload as $nombre => $valor) {
        $info = GD_JWT_CLAIMS[$nombre] ?? null;
        $fila = [
            'claim'       => (string) $nombre,
            'label'       => $info[0] ?? 'Personalizado',
            'description' => $info[1] ?? 'Claim propio de la aplicación que emitió el token.',
            'registered'  => $info !== null,
            'value'       => $valor,
        ];
        if (in_array($nombre, ['exp', 'nbf', 'iat', 'auth_time'], true) && is_numeric($valor)) {
            $fila['date'] = gmdate('c', (int) $valor);
            $fila['relative'] = (int) $valor - $now;
        }
        $claims[] = $fila;
    }

    $estado = 'valid';
    $avisos = [];
    if (isset($payload['exp']) && !is_numeric($payload['exp'])) {
        $avisos[] = 'El claim exp no es una fecha numérica (segundos desde 1970), así que el token no caduca de forma fiable.';
    } elseif (isset($payload['exp']) && (int) $payload['exp'] <= $now) {
        $estado = 'expired';
    }
    if ($estado === 'valid' && isset($payload['nbf']) && is_numeric($payload['nbf']) && (int) $payload['nbf'] > $now) {
        $estado = 'not_yet_valid';
    }
    if (isset($payload['iat']) && is_numeric($payload['iat']) && (int) $payload['iat'] > $now + 60) {
        $avisos[] = 'La fecha de emisión (iat) está en el futuro: revisa el reloj del sistema que generó el token.';
    }
    if (!isset($payload['exp'])) {
        $avisos[] = 'El token no tiene caducidad (exp): si se filtra, seguirá sirviendo indefinidamente.';
    }

    return ['claims' => $claims, 'status' => $estado, 'warnings' => $avisos];
}

// ── DER mínimo para claves y firmas ───────────────────────────

function gd_der_len(int $len): string
{
    if ($len < 0x80) {
        return chr($len);
    }
    $bytes = ltrim(pack('N', $len), "\0");
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function gd_der(int $tag, string $contenido): string
{
    return chr($tag) . gd_der_len(strlen($contenido)) . $contenido;
}

function gd_der_int(string $bin): string
{
    $bin = ltrim($bin, "\0");
    if ($bin === '' || (ord($bin[0]) & 0x80)) {
        $bin = "\0" . $bin;
    }
    return gd_der(0x02, $bin);
}

function gd_pem(string $der, string $tipo): string
{
    return "-----BEGIN $tipo-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END $tipo-----\n";
}

const GD_ED25519_SPKI_PREFIX  = "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00";
const GD_ED25519_PKCS8_PREFIX = "\x30\x2e\x02\x01\x00\x30\x05\x06\x03\x2b\x65\x70\x04\x22\x04\x20";
const GD_EC_CURVE_OID = [
    'P-256' => ["\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07", 32, 'prime256v1'],
    'P-384' => ["\x06\x05\x2b\x81\x04\x00\x22", 48, 'secp384r1'],
    'P-521' => ["\x06\x05\x2b\x81\x04\x00\x23", 66, 'secp521r1'],
];

/** Firma JWS ECDSA (R‖S) → DER para openssl_verify. */
function gd_ecdsa_raw_to_der(string $raw, int $n): ?string
{
    if (strlen($raw) !== 2 * $n) {
        return null;
    }
    return gd_der(0x30, gd_der_int(substr($raw, 0, $n)) . gd_der_int(substr($raw, $n)));
}

/** Firma DER de openssl_sign → R‖S de longitud fija. */
function gd_ecdsa_der_to_raw(string $der, int $n): ?string
{
    $pos = 0;
    $leer = function () use (&$der, &$pos) {
        $tag = ord($der[$pos++] ?? "\xff");
        $len = ord($der[$pos++] ?? "\0");
        if ($len & 0x80) {
            $nb = $len & 0x7f;
            $len = 0;
            for ($i = 0; $i < $nb; $i++) {
                $len = ($len << 8) | ord($der[$pos++] ?? "\0");
            }
        }
        $valor = substr($der, $pos, $len);
        $pos += $len;
        return [$tag, $valor];
    };
    [$tag, $seq] = $leer();
    if ($tag !== 0x30) {
        return null;
    }
    $der = $seq;
    $pos = 0;
    [$t1, $r] = $leer();
    [$t2, $s] = $leer();
    if ($t1 !== 0x02 || $t2 !== 0x02) {
        return null;
    }
    $r = ltrim($r, "\0");
    $s = ltrim($s, "\0");
    if (strlen($r) > $n || strlen($s) > $n) {
        return null;
    }
    return str_pad($r, $n, "\0", STR_PAD_LEFT) . str_pad($s, $n, "\0", STR_PAD_LEFT);
}

// ── Carga de claves ───────────────────────────────────────────

/**
 * Convierte el texto de una clave (PEM, certificado o JWK) en una clave usable.
 * Devuelve ['ok'=>true,'type'=>rsa|ec|eddsa,'key'=>OpenSSLAsymmetricKey|bytes,'bits'?,'curve'?]
 */
function gd_jwt_load_key(string $texto, bool $privada): array
{
    $texto = trim($texto);
    if ($texto === '') {
        return gd_fail($privada ? 'CLAVE_PRIVADA_VACIA' : 'CLAVE_PUBLICA_VACIA', 400);
    }
    if (strlen($texto) > GD_JWT_MAX_KEY) {
        return gd_fail('CLAVE_DEMASIADO_GRANDE', 413);
    }

    if ($texto[0] === '{') {
        $jwk = gd_json_object($texto);
        if ($jwk === null) {
            return gd_fail('JWK_INVALIDA');
        }
        if ($privada) {
            return gd_fail('JWK_PRIVADA_NO_ADMITIDA');
        }
        return gd_jwk_to_key($jwk);
    }

    if (!preg_match('/-----BEGIN ([A-Z0-9 ]+)-----(.+?)-----END \1-----/s', $texto, $m)) {
        return gd_fail('CLAVE_FORMATO');
    }
    $der = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
    if ($der === false) {
        return gd_fail('CLAVE_FORMATO');
    }

    if (!$privada && strncmp($der, GD_ED25519_SPKI_PREFIX, 12) === 0 && strlen($der) === 44) {
        return ['ok' => true, 'type' => 'eddsa', 'key' => substr($der, 12)];
    }
    if ($privada && strncmp($der, GD_ED25519_PKCS8_PREFIX, 16) === 0 && strlen($der) === 48) {
        $par = sodium_crypto_sign_seed_keypair(substr($der, 16));
        return ['ok' => true, 'type' => 'eddsa', 'key' => sodium_crypto_sign_secretkey($par)];
    }

    if (strpos($m[1], 'ENCRYPTED') !== false) {
        return gd_fail('CLAVE_CIFRADA');
    }
    if (!$privada && $m[1] === 'RSA PUBLIC KEY') {
        // PKCS#1 → SPKI: OpenSSL 1.1 (PHP 7.4) solo lee claves públicas en formato SPKI.
        $spki = gd_der(0x30, gd_der(0x30, "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00") . gd_der(0x03, "\0" . $der));
        $m[0] = gd_pem($spki, 'PUBLIC KEY');
        $m[1] = 'PUBLIC KEY';
    }
    if ($privada && strpos($m[1], 'PRIVATE') === false) {
        return gd_fail('CLAVE_NO_PRIVADA');
    }

    $clave = $privada ? @openssl_pkey_get_private($m[0]) : @openssl_pkey_get_public($m[0]);
    if ($clave === false && !$privada && strpos($m[1], 'PRIVATE') !== false) {
        $priv = @openssl_pkey_get_private($m[0]);
        $clave = $priv ? openssl_pkey_get_public(openssl_pkey_get_details($priv)['key']) : false;
    }
    while (openssl_error_string() !== false) {
    }
    if ($clave === false) {
        return gd_fail('CLAVE_FORMATO');
    }

    $det = openssl_pkey_get_details($clave);
    if ($det['type'] === OPENSSL_KEYTYPE_RSA) {
        return ['ok' => true, 'type' => 'rsa', 'key' => $clave, 'bits' => $det['bits']];
    }
    if ($det['type'] === OPENSSL_KEYTYPE_EC) {
        return ['ok' => true, 'type' => 'ec', 'key' => $clave, 'curve' => $det['ec']['curve_name'] ?? ''];
    }
    return gd_fail('CLAVE_TIPO');
}

function gd_jwk_to_key(array $jwk): array
{
    $kty = $jwk['kty'] ?? '';
    $campo = function ($k) use ($jwk) {
        return isset($jwk[$k]) && is_string($jwk[$k]) ? gd_b64url_decode($jwk[$k]) : null;
    };

    if ($kty === 'RSA') {
        $n = $campo('n');
        $e = $campo('e');
        if (!$n || !$e) {
            return gd_fail('JWK_INVALIDA');
        }
        $rsa = gd_der(0x30, gd_der_int($n) . gd_der_int($e));
        $spki = gd_der(0x30, gd_der(0x30, "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00") . gd_der(0x03, "\0" . $rsa));
        return gd_jwt_load_key(gd_pem($spki, 'PUBLIC KEY'), false);
    }
    if ($kty === 'EC') {
        $curva = GD_EC_CURVE_OID[$jwk['crv'] ?? ''] ?? null;
        $x = $campo('x');
        $y = $campo('y');
        if (!$curva || !$x || !$y || strlen($x) !== $curva[1] || strlen($y) !== $curva[1]) {
            return gd_fail('JWK_INVALIDA');
        }
        $alg = gd_der(0x30, "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" . $curva[0]);
        $spki = gd_der(0x30, $alg . gd_der(0x03, "\0\x04" . $x . $y));
        return gd_jwt_load_key(gd_pem($spki, 'PUBLIC KEY'), false);
    }
    if ($kty === 'OKP' && ($jwk['crv'] ?? '') === 'Ed25519') {
        $x = $campo('x');
        if (!$x || strlen($x) !== 32) {
            return gd_fail('JWK_INVALIDA');
        }
        return ['ok' => true, 'type' => 'eddsa', 'key' => $x];
    }
    return gd_fail('JWK_TIPO');
}

/** Comprueba que la clave cargada corresponde al algoritmo (evita la confusión de algoritmos). */
function gd_jwt_key_matches(string $alg, array $clave, bool $firmar): ?array
{
    $familia = GD_JWT_ALGS[$alg][0];
    $esperado = ['rsa' => 'rsa', 'ec' => 'ec', 'eddsa' => 'eddsa'][$familia] ?? null;
    if ($esperado === null || $clave['type'] !== $esperado) {
        return gd_fail('CLAVE_NO_COINCIDE');
    }
    if ($familia === 'ec' && $clave['curve'] !== GD_EC_CURVES[$alg]) {
        return gd_fail('CURVA_NO_COINCIDE');
    }
    if ($familia === 'rsa' && $firmar && $clave['bits'] < 2048) {
        return gd_fail('RSA_CORTA');
    }
    return null;
}

function gd_jwt_secret(string $secreto, bool $esBase64): array
{
    if ($secreto === '') {
        return gd_fail('SECRETO_VACIO', 400);
    }
    if (strlen($secreto) > GD_JWT_MAX_KEY) {
        return gd_fail('CLAVE_DEMASIADO_GRANDE', 413);
    }
    if ($esBase64) {
        $limpio = preg_replace('/\s+/', '', $secreto);
        $bin = preg_match('/^[A-Za-z0-9+\/_-]+={0,2}$/', $limpio)
            ? base64_decode(strtr(rtrim($limpio, '='), '-_', '+/') . str_repeat('=', (4 - strlen(rtrim($limpio, '=')) % 4) % 4), true)
            : false;
        if ($bin === false || $bin === '') {
            return gd_fail('SECRETO_B64');
        }
        return ['ok' => true, 'key' => $bin];
    }
    return ['ok' => true, 'key' => $secreto];
}

// ── JWKS (con protección contra SSRF) ─────────────────────────

function gd_ip_publica(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $n = ip2long($ip);
        $bloqueados = [['100.64.0.0', 10], ['192.0.0.0', 24], ['198.18.0.0', 15], ['169.254.0.0', 16], ['0.0.0.0', 8], ['127.0.0.0', 8]];
        foreach ($bloqueados as [$red, $bits]) {
            $mask = -1 << (32 - $bits);
            if (($n & $mask) === (ip2long($red) & $mask)) {
                return false;
            }
        }
    } else {
        $bin = inet_pton($ip);
        if ($bin === false || (ord($bin[0]) & 0xfe) === 0xfc || (ord($bin[0]) === 0xfe && (ord($bin[1]) & 0xc0) === 0x80)) {
            return false;
        }
    }
    return true;
}

function gd_https_get_json(string $url): array
{
    $p = parse_url($url);
    if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])
        || (isset($p['port']) && (int) $p['port'] !== 443) || strlen($url) > 2048) {
        return gd_fail('JWKS_URL');
    }
    $host = strtolower(trim($p['host'], '[]'));

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips = [$host];
    } else {
        if (!preg_match('/^[a-z0-9.-]+$/', $host) || in_array($host, ['localhost'], true) || substr($host, -6) === '.local') {
            return gd_fail('JWKS_DESTINO');
        }
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $r) {
            if (!empty($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }
    }
    if (!$ips) {
        return gd_fail('JWKS_DNS', 502);
    }
    foreach ($ips as $ip) {
        if (!gd_ip_publica($ip)) {
            return gd_fail('JWKS_DESTINO');
        }
    }
    $ipFijada = $ips[0];
    $resolve = $host . ':443:' . (strpos($ipFijada, ':') !== false ? '[' . $ipFijada . ']' : $ipFijada);

    $cuerpo = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RESOLVE         => [$resolve],
        CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION  => false,
        CURLOPT_SSL_VERIFYPEER  => true,
        CURLOPT_SSL_VERIFYHOST  => 2,
        CURLOPT_CONNECTTIMEOUT  => 3,
        CURLOPT_TIMEOUT         => 5,
        CURLOPT_PROXY           => '',
        CURLOPT_USERAGENT       => 'GeneradorData-JWKS/1.0',
        CURLOPT_HTTPHEADER      => ['Accept: application/json'],
        CURLOPT_WRITEFUNCTION   => function ($ch, $datos) use (&$cuerpo) {
            if (strlen($cuerpo) + strlen($datos) > GD_JWKS_MAX_BYTES) {
                return -1;
            }
            $cuerpo .= $datos;
            return strlen($datos);
        },
    ]);
    $ok = curl_exec($ch);
    $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno === CURLE_WRITE_ERROR) {
        return gd_fail('JWKS_GRANDE', 502);
    }
    if ($ok === false) {
        return gd_fail('JWKS_CONEXION', 502);
    }
    if ($estado >= 300 && $estado < 400) {
        return gd_fail('JWKS_REDIRECCION', 502);
    }
    if ($estado !== 200) {
        return gd_fail('JWKS_HTTP', 502);
    }
    $json = gd_json_object($cuerpo);
    return $json === null ? gd_fail('JWKS_JSON', 502) : ['ok' => true, 'json' => $json];
}

function gd_jwks_fetch(string $url): array
{
    $r = gd_https_get_json(trim($url));
    if (!$r['ok']) {
        return $r;
    }
    $json = $r['json'];
    if (!isset($json['keys']) && isset($json['jwks_uri']) && is_string($json['jwks_uri'])) {
        $r = gd_https_get_json($json['jwks_uri']);
        if (!$r['ok']) {
            return $r;
        }
        $json = $r['json'];
    }
    if (!isset($json['keys']) || !is_array($json['keys']) || !$json['keys']) {
        return gd_fail('JWKS_SIN_CLAVES', 502);
    }
    return ['ok' => true, 'keys' => array_slice(array_values(array_filter($json['keys'], 'is_array')), 0, GD_JWKS_MAX_KEYS)];
}

// ── Firma y verificación ──────────────────────────────────────

function gd_jwt_sign_raw(string $alg, string $input, $clave): ?string
{
    [$familia, $hash, $n] = GD_JWT_ALGS[$alg];
    switch ($familia) {
        case 'hmac':
            return hash_hmac($hash, $input, $clave, true);
        case 'rsa':
            return openssl_sign($input, $firma, $clave, $hash) ? $firma : null;
        case 'ec':
            return openssl_sign($input, $der, $clave, $hash) ? gd_ecdsa_der_to_raw($der, $n) : null;
        case 'eddsa':
            return sodium_crypto_sign_detached($input, $clave);
    }
    return null;
}

function gd_jwt_check_raw(string $alg, string $input, string $firma, $clave): bool
{
    [$familia, $hash, $n] = GD_JWT_ALGS[$alg];
    switch ($familia) {
        case 'hmac':
            return hash_equals(hash_hmac($hash, $input, $clave, true), $firma);
        case 'rsa':
            return openssl_verify($input, $firma, $clave, $hash) === 1;
        case 'ec':
            $der = gd_ecdsa_raw_to_der($firma, $n);
            return $der !== null && openssl_verify($input, $der, $clave, $hash) === 1;
        case 'eddsa':
            return strlen($firma) === SODIUM_CRYPTO_SIGN_BYTES && strlen($clave) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                && sodium_crypto_sign_verify_detached($firma, $input, $clave);
    }
    return false;
}

/** Análisis sin verificar la firma: encabezado, carga útil, claims y avisos. */
function gd_jwt_decode(string $token): array
{
    $jwt = gd_jwt_parse($token);
    if (!$jwt['ok']) {
        return $jwt;
    }
    $an = gd_jwt_claims($jwt['payload'], time());
    $avisos = $an['warnings'];
    if ($jwt['alg'] === 'none') {
        array_unshift($avisos, 'El token usa alg "none": no tiene firma y cualquiera puede modificarlo. Nunca debe aceptarse.');
    }
    if (GD_JWT_ALGS[$jwt['alg']][0] === 'pss') {
        $avisos[] = 'Los tokens ' . $jwt['alg'] . ' (RSA-PSS) se pueden decodificar, pero esta herramienta no verifica su firma.';
    }
    return [
        'ok'       => true,
        'header'   => $jwt['header'],
        'payload'  => $jwt['payload'],
        'alg'      => $jwt['alg'],
        'parts'    => $jwt['parts'],
        'claims'   => $an['claims'],
        'token_status' => $an['status'],
        'warnings' => $avisos,
        'verified' => null,
    ];
}

/**
 * $material: ['secret' => string, 'secret_b64' => bool] | ['public_key' => string] | ['jwks_uri' => string]
 */
function gd_jwt_verify(string $token, array $material): array
{
    $res = gd_jwt_decode($token);
    if (!$res['ok']) {
        return $res;
    }
    $jwt = gd_jwt_parse($token);
    $alg = $jwt['alg'];
    $familia = GD_JWT_ALGS[$alg][0];

    $fallo = function (string $code) use ($res) {
        $res['verified'] = false;
        $res['verify_error'] = $code;
        return $res;
    };

    if ($familia === 'none') {
        return $fallo('ALG_NONE');
    }
    if ($familia === 'pss') {
        return $fallo('ALG_PSS');
    }
    if (isset($jwt['header']['crit'])) {
        return $fallo('CRIT');
    }

    if ($familia === 'hmac') {
        if (!isset($material['secret'])) {
            return $fallo('HMAC_NECESITA_SECRETO');
        }
        $sec = gd_jwt_secret($material['secret'], !empty($material['secret_b64']));
        if (!$sec['ok']) {
            return $sec;
        }
        if (strpos(ltrim($material['secret']), '-----BEGIN') === 0) {
            $res['warnings'][] = 'El secreto parece una clave PEM. Un token HS* firmado con una clave pública suele indicar un ataque de confusión de algoritmos.';
        }
        $res['verified'] = gd_jwt_check_raw($alg, $jwt['signing_input'], $jwt['signature'], $sec['key']);
        if (!$res['verified']) {
            $res['verify_error'] = 'FIRMA_NO_COINCIDE';
        }
        return $res;
    }

    if (isset($material['secret'])) {
        return $fallo('ASIMETRICO_NECESITA_CLAVE');
    }

    $candidatas = [];
    if (isset($material['public_key'])) {
        $clave = gd_jwt_load_key($material['public_key'], false);
        if (!$clave['ok']) {
            return $clave;
        }
        $candidatas[] = $clave;
    } elseif (isset($material['jwks_uri'])) {
        $jwks = gd_jwks_fetch($material['jwks_uri']);
        if (!$jwks['ok']) {
            return $jwks;
        }
        $kid = $jwt['header']['kid'] ?? null;
        foreach ($jwks['keys'] as $jwk) {
            if (($jwk['use'] ?? 'sig') !== 'sig' || (isset($jwk['alg']) && $jwk['alg'] !== $alg)) {
                continue;
            }
            if ($kid !== null && ($jwk['kid'] ?? null) !== $kid) {
                continue;
            }
            $clave = gd_jwk_to_key($jwk);
            if ($clave['ok']) {
                $candidatas[] = $clave;
            }
        }
        if (!$candidatas) {
            return $fallo($kid !== null ? 'JWKS_KID' : 'JWKS_SIN_COMPATIBLE');
        }
    } else {
        return $fallo('ASIMETRICO_NECESITA_CLAVE');
    }

    foreach ($candidatas as $clave) {
        $error = gd_jwt_key_matches($alg, $clave, false);
        if ($error !== null) {
            if (count($candidatas) === 1) {
                return $fallo($error['error']);
            }
            continue;
        }
        if (gd_jwt_check_raw($alg, $jwt['signing_input'], $jwt['signature'], $clave['key'])) {
            $res['verified'] = true;
            if ($clave['type'] === 'rsa' && $clave['bits'] < 2048) {
                $res['warnings'][] = 'La clave RSA tiene menos de 2048 bits: se considera insegura.';
            }
            return $res;
        }
    }
    return $fallo('FIRMA_NO_COINCIDE');
}

/**
 * Crea y firma un JWT. $material: ['secret' =>, 'secret_b64' =>] | ['private_key' =>]
 */
function gd_jwt_encode(string $headerJson, string $payloadJson, string $alg, array $material): array
{
    if (!isset(GD_JWT_ALGS[$alg])) {
        return gd_fail('JWT_ALG_DESCONOCIDO');
    }
    $familia = GD_JWT_ALGS[$alg][0];
    if ($familia === 'none') {
        return gd_fail('FIRMAR_NONE');
    }
    if ($familia === 'pss') {
        return gd_fail('ALG_PSS');
    }
    if (strlen($headerJson) > GD_JWT_MAX_JSON || strlen($payloadJson) > GD_JWT_MAX_JSON) {
        return gd_fail('JSON_DEMASIADO_GRANDE', 413);
    }

    $header = trim($headerJson) === '' ? [] : gd_json_object($headerJson);
    if ($header === null) {
        return gd_fail('ENCODE_HEADER_JSON');
    }
    $payload = gd_json_object(trim($payloadJson) === '' ? '{}' : $payloadJson);
    if ($payload === null) {
        return gd_fail('ENCODE_PAYLOAD_JSON');
    }
    if (isset($header['crit'])) {
        return gd_fail('CRIT');
    }
    $header = ['alg' => $alg] + $header;
    $header['alg'] = $alg;
    if (!isset($header['typ'])) {
        $header['typ'] = 'JWT';
    }

    $avisos = [];
    if ($familia === 'hmac') {
        if (!isset($material['secret'])) {
            return gd_fail('SECRETO_VACIO', 400);
        }
        $sec = gd_jwt_secret($material['secret'], !empty($material['secret_b64']));
        if (!$sec['ok']) {
            return $sec;
        }
        $clave = $sec['key'];
        $minimo = GD_JWT_ALGS[$alg][2];
        if (strlen($clave) < $minimo) {
            $avisos[] = "El secreto tiene " . strlen($clave) . " bytes; para $alg se recomiendan al menos $minimo (RFC 7518). Un secreto corto se puede adivinar por fuerza bruta.";
        }
    } else {
        $carga = gd_jwt_load_key($material['private_key'] ?? '', true);
        if (!$carga['ok']) {
            return $carga;
        }
        $error = gd_jwt_key_matches($alg, $carga, true);
        if ($error !== null) {
            return $error;
        }
        $clave = $carga['key'];
    }

    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $partes = [gd_b64url_encode(json_encode($header, $flags)), gd_b64url_encode(json_encode($payload, $flags))];
    $firma = gd_jwt_sign_raw($alg, $partes[0] . '.' . $partes[1], $clave);
    while (openssl_error_string() !== false) {
    }
    if ($firma === null) {
        return gd_fail('FIRMA_FALLIDA', 500);
    }
    $partes[] = gd_b64url_encode($firma);

    $an = gd_jwt_claims($payload, time());
    return [
        'ok'       => true,
        'token'    => implode('.', $partes),
        'parts'    => $partes,
        'header'   => $header,
        'payload'  => $payload,
        'alg'      => $alg,
        'claims'   => $an['claims'],
        'token_status' => $an['status'],
        'warnings' => array_merge($avisos, $an['warnings']),
    ];
}

// ── Mensajes ──────────────────────────────────────────────────

function gd_jwt_error_message(string $code): string
{
    $m = [
        'JWT_VACIO'            => 'Pega un JWT para analizarlo.',
        'JWT_DEMASIADO_GRANDE' => 'El token es demasiado grande (máximo 64 KB).',
        'JWT_JWE'              => 'El token tiene 5 partes: es un JWE (token cifrado). Esta herramienta solo decodifica JWT firmados (JWS), que tienen 3 partes.',
        'JWT_PARTES'           => 'Un JWT debe tener exactamente 3 partes separadas por puntos: encabezado.carga.firma.',
        'JWT_HEADER_B64'       => 'El encabezado no está codificado correctamente en Base64URL.',
        'JWT_PAYLOAD_B64'      => 'La carga útil no está codificada correctamente en Base64URL.',
        'JWT_FIRMA_B64'        => 'La firma no está codificada correctamente en Base64URL.',
        'JWT_HEADER_JSON'      => 'El encabezado no es un objeto JSON válido.',
        'JWT_PAYLOAD_JSON'     => 'La carga útil no es un objeto JSON válido.',
        'JWT_ALG_DESCONOCIDO'  => 'El algoritmo (alg) del encabezado falta o no es un algoritmo JWS conocido.',
        'ALG_NONE'             => 'El token usa alg "none": no tiene firma, así que no se puede verificar.',
        'ALG_PSS'              => 'Esta herramienta no verifica ni firma tokens RSA-PSS (PS256, PS384, PS512).',
        'CRIT'                 => 'El encabezado incluye extensiones críticas (crit) que esta herramienta no admite, así que la firma no se puede dar por válida.',
        'HMAC_NECESITA_SECRETO' => 'Este token usa un algoritmo HMAC (HS*): verifícalo con el secreto, no con una clave pública.',
        'ASIMETRICO_NECESITA_CLAVE' => 'Este token usa un algoritmo de clave pública: verifícalo con una clave pública o un endpoint JWKS, no con un secreto.',
        'FIRMA_NO_COINCIDE'    => 'La firma no coincide: el token se modificó o se firmó con otra clave.',
        'SECRETO_VACIO'        => 'Introduce el secreto HMAC.',
        'SECRETO_B64'          => 'El secreto está marcado como «Codificado en Base64», pero no es Base64 válido.',
        'CLAVE_PUBLICA_VACIA'  => 'Introduce la clave pública (PEM o JWK).',
        'CLAVE_PRIVADA_VACIA'  => 'Introduce la clave privada en formato PEM.',
        'CLAVE_DEMASIADO_GRANDE' => 'La clave o el secreto es demasiado grande (máximo 16 KB).',
        'CLAVE_FORMATO'        => 'No se reconoce la clave. Pégala en formato PEM (-----BEGIN PUBLIC KEY-----) o como JWK en JSON.',
        'CLAVE_CIFRADA'        => 'La clave privada está protegida con contraseña. Usa una clave sin cifrar (solo para pruebas).',
        'CLAVE_NO_PRIVADA'     => 'Para firmar hace falta una clave privada (-----BEGIN PRIVATE KEY-----), no una pública.',
        'CLAVE_TIPO'           => 'Tipo de clave no admitido. Se admiten claves RSA, EC (P-256, P-384, P-521) y Ed25519.',
        'CLAVE_NO_COINCIDE'    => 'La clave no corresponde al algoritmo del token (por ejemplo, una clave EC para un token RS256).',
        'CURVA_NO_COINCIDE'    => 'La curva de la clave no corresponde al algoritmo: ES256 usa P-256, ES384 usa P-384 y ES512 usa P-521.',
        'RSA_CORTA'            => 'La clave RSA tiene menos de 2048 bits; no se admite para firmar.',
        'JWK_INVALIDA'         => 'La JWK no es válida o le faltan campos obligatorios.',
        'JWK_TIPO'             => 'Tipo de JWK no admitido. Se admiten RSA, EC y OKP (Ed25519).',
        'JWK_PRIVADA_NO_ADMITIDA' => 'Para firmar, pega la clave privada en formato PEM.',
        'JWKS_URL'             => 'La URL del JWKS debe empezar por https:// y no puede llevar usuario, contraseña ni otro puerto distinto de 443.',
        'JWKS_DESTINO'         => 'Por seguridad, no se permiten URL de JWKS que apunten a redes privadas o locales.',
        'JWKS_DNS'             => 'No se ha podido resolver el dominio del JWKS.',
        'JWKS_CONEXION'        => 'No se ha podido descargar el JWKS (conexión fallida, tiempo agotado o certificado no válido).',
        'JWKS_REDIRECCION'     => 'La URL del JWKS responde con una redirección. Por seguridad no se siguen: usa la URL final.',
        'JWKS_HTTP'            => 'El servidor del JWKS no respondió correctamente.',
        'JWKS_GRANDE'          => 'La respuesta del JWKS es demasiado grande.',
        'JWKS_JSON'            => 'La URL no devuelve un JSON válido.',
        'JWKS_SIN_CLAVES'      => 'La URL no devuelve un JWKS (falta la lista "keys") ni un documento OpenID con jwks_uri.',
        'JWKS_KID'             => 'El JWKS no contiene ninguna clave con el kid del encabezado del token.',
        'JWKS_SIN_COMPATIBLE'  => 'El JWKS no contiene ninguna clave compatible con el algoritmo del token.',
        'FIRMAR_NONE'          => 'No se pueden crear tokens sin firma (alg "none").',
        'JSON_DEMASIADO_GRANDE' => 'El encabezado o la carga útil son demasiado grandes (máximo 32 KB).',
        'ENCODE_HEADER_JSON'   => 'El encabezado no es un objeto JSON válido.',
        'ENCODE_PAYLOAD_JSON'  => 'La carga útil no es un objeto JSON válido.',
        'FIRMA_FALLIDA'        => 'No se ha podido firmar el token con esa clave.',
        'DEMASIADO_GRANDE'     => 'La petición es demasiado grande.',
        'METODO'               => 'Método no permitido.',
        'ORIGEN'               => 'Petición no permitida.',
        'ACCION'               => 'Acción no válida.',
        'LIMITE'               => 'Has hecho demasiadas operaciones seguidas. Espera unos minutos y vuelve a intentarlo.',
        'SERVIDOR'             => 'No se ha podido procesar la petición por un error interno.',
    ];
    return $m[$code] ?? $m['SERVIDOR'];
}
