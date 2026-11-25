<?php
/**
 * base64_pdf.php
 * Conversión Base64 ↔ PDF con validación estricta. Solo define funciones:
 * se carga desde base64controller.php (junto con security.php) y no produce salida.
 * Ningún dato del usuario se escribe en disco ni se devuelve en los mensajes.
 */

defined('GD_APP') || exit;

const GD_PDF_MAX_BYTES = 10 * 1024 * 1024;
const GD_B64_MAX_CHARS = 14 * 1024 * 1024;
const GD_PDF_SCAN_BYTES = 1024;

/**
 * Limpia el texto pegado y lo deja como Base64 estándar con relleno.
 * Acepta Data URI, saltos de línea/espacios y la variante base64url.
 */
function gd_b64_normalize(string $input): array
{
    if (strlen($input) > GD_B64_MAX_CHARS) {
        return gd_fail('B64_DEMASIADO_GRANDE', 413);
    }

    $s = trim($input);
    if ($s === '') {
        return gd_fail('B64_VACIO', 400);
    }

    $s = preg_replace('#^data:(?:application/(?:pdf|octet-stream)|binary/octet-stream)?(?:;[a-z0-9._=-]+)*;base64,#i', '', $s, 1);
    $s = preg_replace('/\s+/', '', $s);
    $s = strtr($s, '-_', '+/');

    if ($s === '' || !preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $s)) {
        return gd_fail('B64_CARACTERES');
    }

    $resto = strlen(rtrim($s, '=')) % 4;
    if ($resto === 1) {
        return gd_fail('B64_LONGITUD');
    }
    $s = rtrim($s, '=');
    if ($resto > 0) {
        $s .= str_repeat('=', 4 - $resto);
    }

    return ['ok' => true, 'base64' => $s];
}

/**
 * Identifica por sus primeros bytes qué es un binario que no resultó ser PDF,
 * para dar un mensaje útil. Solo devuelve etiquetas fijas, nunca contenido.
 */
function gd_guess_type(string $bin): string
{
    $firmas = [
        "\x89PNG\r\n\x1a\n" => 'una imagen PNG',
        "\xFF\xD8\xFF"      => 'una imagen JPEG',
        'GIF8'              => 'una imagen GIF',
        "PK\x03\x04"        => 'un archivo ZIP o un documento de Office (docx, xlsx…)',
        "\xD0\xCF\x11\xE0"  => 'un documento antiguo de Office (doc, xls…)',
        'MZ'                => 'un programa ejecutable de Windows',
        "\x7FELF"           => 'un programa ejecutable',
        '{\\rtf'            => 'un documento RTF',
    ];
    foreach ($firmas as $firma => $tipo) {
        if (strncmp($bin, $firma, strlen($firma)) === 0) {
            return $tipo;
        }
    }
    if (strncmp($bin, 'RIFF', 4) === 0 && substr($bin, 8, 4) === 'WEBP') {
        return 'una imagen WebP';
    }

    $inicio = ltrim(substr($bin, 0, 512));
    if (preg_match('/^(<!doctype html|<html|<script|<svg|<\?xml)/i', $inicio)) {
        return 'un documento HTML, SVG o XML';
    }
    if (preg_match('/^\s*[\[{]/', $inicio)) {
        return 'texto JSON';
    }
    if ($inicio !== '' && preg_match('//u', $inicio) && !preg_match('/[\x00-\x08\x0E-\x1F]/', $inicio)) {
        return 'texto plano';
    }
    return '';
}

/**
 * Comprueba que un binario es un PDF íntegro y extrae información básica.
 */
function gd_pdf_inspect(string $bin): array
{
    $size = strlen($bin);
    if ($size === 0) {
        return gd_fail('PDF_VACIO');
    }
    if ($size > GD_PDF_MAX_BYTES) {
        return gd_fail('PDF_DEMASIADO_GRANDE', 413);
    }

    $cabecera = substr($bin, 0, GD_PDF_SCAN_BYTES);
    if (!preg_match('/%PDF-(\d\.\d)/', $cabecera, $m)) {
        return gd_fail('PDF_SIN_CABECERA', 415) + ['parece' => gd_guess_type($bin)];
    }
    $version = $m[1];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if ($finfo->buffer($bin) !== 'application/pdf') {
        return gd_fail('PDF_TIPO', 415);
    }

    if (strpos(substr($bin, -GD_PDF_SCAN_BYTES), '%%EOF') === false) {
        return gd_fail('PDF_INCOMPLETO');
    }

    $paginas = preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $bin);
    $cifrado = strpos($bin, '/Encrypt') !== false;
    $activo  = (bool) preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|RichMedia|SubmitForm|ImportData)(?![a-zA-Z])#', $bin);

    return [
        'ok'   => true,
        'info' => [
            'size'      => $size,
            'version'   => $version,
            'pages'     => $paginas,
            'encrypted' => $cifrado,
            'active'    => $activo,
        ],
    ];
}

/**
 * Base64 pegado por el usuario → PDF validado.
 * Devuelve el Base64 limpio: exactamente los bytes que se han validado.
 */
function gd_b64_to_pdf(string $input): array
{
    $norm = gd_b64_normalize($input);
    if (!$norm['ok']) {
        return $norm;
    }

    if ((int) (strlen($norm['base64']) / 4 * 3) > GD_PDF_MAX_BYTES + 3) {
        return gd_fail('PDF_DEMASIADO_GRANDE', 413);
    }

    $bin = base64_decode($norm['base64'], true);
    if ($bin === false) {
        return gd_fail('B64_INVALIDO');
    }

    $pdf = gd_pdf_inspect($bin);
    if (!$pdf['ok']) {
        return $pdf;
    }

    return ['ok' => true, 'info' => $pdf['info'], 'base64' => $norm['base64']];
}

/**
 * PDF subido ($_FILES['pdf']) → Base64. El temporal lo borra PHP al terminar.
 */
function gd_pdf_to_b64(array $file): array
{
    if (!isset($file['error'], $file['tmp_name'], $file['size']) || is_array($file['error'])) {
        return gd_fail('PDF_SIN_ARCHIVO', 400);
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return gd_fail('PDF_DEMASIADO_GRANDE', 413);
        case UPLOAD_ERR_PARTIAL:
            return gd_fail('PDF_SUBIDA_PARCIAL', 400);
        case UPLOAD_ERR_NO_FILE:
            return gd_fail('PDF_SIN_ARCHIVO', 400);
        default:
            error_log('base64_pdf: error de subida ' . (int) $file['error']);
            return gd_fail('SERVIDOR', 500);
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return gd_fail('PDF_SIN_ARCHIVO', 400);
    }
    if ($file['size'] <= 0) {
        return gd_fail('PDF_VACIO');
    }
    if ($file['size'] > GD_PDF_MAX_BYTES) {
        return gd_fail('PDF_DEMASIADO_GRANDE', 413);
    }

    $nombre = isset($file['name']) && is_string($file['name']) ? basename($file['name']) : '';
    if (strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'pdf') {
        return gd_fail('PDF_EXTENSION', 415);
    }

    $bin = file_get_contents($file['tmp_name'], false, null, 0, GD_PDF_MAX_BYTES + 1);
    if ($bin === false) {
        error_log('base64_pdf: no se pudo leer el temporal');
        return gd_fail('SERVIDOR', 500);
    }

    $pdf = gd_pdf_inspect($bin);
    if (!$pdf['ok']) {
        if ($pdf['error'] === 'PDF_SIN_CABECERA') {
            $pdf['error'] = 'ARCHIVO_NO_PDF';
        }
        return $pdf;
    }

    $limpio = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($nombre, PATHINFO_FILENAME));
    $limpio = substr(trim($limpio, '._-'), 0, 96) ?: 'documento';

    return [
        'ok'       => true,
        'info'     => $pdf['info'],
        'base64'   => base64_encode($bin),
        'filename' => $limpio . '.pdf',
    ];
}

/**
 * Mensajes al usuario: tabla cerrada, sin datos de la petición.
 */
function gd_b64_error_message(string $code, string $parece = ''): string
{
    $mensajes = [
        'B64_VACIO'            => 'Pega un texto en Base64 para convertirlo.',
        'B64_DEMASIADO_GRANDE' => 'El texto es demasiado largo: el PDF resultante superaría el límite de 10 MB.',
        'B64_CARACTERES'       => 'El texto contiene caracteres que no son Base64. Revisa que hayas copiado solo el código, sin comillas, etiquetas ni otro texto alrededor.',
        'B64_LONGITUD'         => 'La longitud del texto no es válida para Base64: probablemente falta o sobra algún carácter al copiarlo.',
        'B64_INVALIDO'         => 'El texto no se puede decodificar como Base64.',
        'PDF_VACIO'            => 'El archivo está vacío.',
        'PDF_DEMASIADO_GRANDE' => 'El PDF supera el tamaño máximo permitido de 10 MB.',
        'PDF_SIN_CABECERA'     => 'El Base64 se decodifica correctamente, pero el resultado no es un PDF.',
        'PDF_TIPO'             => 'El contenido tiene cabecera de PDF, pero su estructura no corresponde a un PDF válido.',
        'PDF_INCOMPLETO'       => 'El PDF está incompleto: falta el final del archivo. Probablemente el Base64 se cortó al copiarlo.',
        'ARCHIVO_NO_PDF'       => 'El archivo tiene extensión .pdf, pero su contenido no es un PDF.',
        'PDF_EXTENSION'        => 'Solo se admiten archivos con extensión .pdf.',
        'PDF_SIN_ARCHIVO'      => 'Selecciona un archivo PDF para convertirlo.',
        'PDF_SUBIDA_PARCIAL'   => 'El archivo no llegó completo al servidor. Vuelve a intentarlo.',
        'METODO'               => 'Método no permitido.',
        'ORIGEN'               => 'Petición no permitida.',
        'ACCION'               => 'Acción no válida.',
        'LIMITE'               => 'Has hecho demasiadas conversiones seguidas. Espera unos minutos y vuelve a intentarlo.',
        'SERVIDOR'             => 'No se ha podido procesar el archivo por un error interno. Inténtalo de nuevo más tarde.',
    ];

    $texto = $mensajes[$code] ?? $mensajes['SERVIDOR'];
    if (($code === 'PDF_SIN_CABECERA' || $code === 'ARCHIVO_NO_PDF') && $parece !== '') {
        $texto .= ' Parece ' . $parece . '.';
    }
    return $texto;
}
