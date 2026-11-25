<?php
/**
 * security.php
 * Utilidades comunes a los controladores AJAX (base64controller.php, jwtcontroller.php):
 * respuesta JSON, guarda de la petición y límite de peticiones por IP.
 */

defined('GD_APP') || exit;

function gd_fail(string $code, int $status = 422): array
{
    return ['ok' => false, 'error' => $code, 'status' => $status];
}

function gd_json_headers(): void
{
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    header('Referrer-Policy: same-origin');
}

/**
 * Envía el resultado y termina. $mensaje traduce un código de error a texto
 * (recibe el array de error completo, para mensajes con detalle).
 */
function gd_respond(array $resultado, callable $mensaje): void
{
    if ($resultado['ok']) {
        unset($resultado['status']);
        http_response_code(200);
    } else {
        http_response_code($resultado['status'] ?? 400);
        $resultado = [
            'ok'      => false,
            'error'   => $resultado['error'],
            'message' => $mensaje($resultado),
        ];
    }
    echo json_encode($resultado, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function gd_bytes_ini(string $valor): int
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

/**
 * Comprobaciones previas comunes. Devuelve null si la petición es aceptable
 * o un array de error (gd_fail) si no lo es.
 */
function gd_ajax_guard(): ?array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        return gd_fail('METODO', 405);
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        if ($host . ($port ? ':' . $port : '') !== ($_SERVER['HTTP_HOST'] ?? '')) {
            return gd_fail('ORIGEN', 403);
        }
    }
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
        return gd_fail('ORIGEN', 403);
    }

    $limitePost = gd_bytes_ini((string) ini_get('post_max_size'));
    if ($limitePost > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limitePost) {
        return gd_fail('DEMASIADO_GRANDE', 413);
    }

    return null;
}

/**
 * Límite de peticiones por IP (ventana deslizante) en el directorio temporal.
 * Cada controlador usa su propio $bucket. Devuelve false si se ha superado.
 */
function gd_rate_limit(string $ip, int $max = 20, int $window = 600, string $bucket = ''): bool
{
    $dir = sys_get_temp_dir() . '/gd_ratelimit';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return true;
    }

    $fh = @fopen($dir . '/' . hash('sha256', $bucket . '|' . $ip), 'c+');
    if ($fh === false) {
        return true;
    }

    flock($fh, LOCK_EX);
    $ahora = time();
    $marcas = array_filter(
        array_map('intval', explode(',', (string) stream_get_contents($fh))),
        function ($t) use ($ahora, $window) { return $t > $ahora - $window; }
    );
    $permitido = count($marcas) < $max;
    if ($permitido) {
        $marcas[] = $ahora;
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, implode(',', $marcas));
    flock($fh, LOCK_UN);
    fclose($fh);

    return $permitido;
}
