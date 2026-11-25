<?php
/**
 * build/layout.php
 * Copia la cabecera, el menú, la barra de herramientas y el pie de partials/layout.html
 * a todas las páginas .html de la raíz que tengan las marcas <!-- @inicio X --> … <!-- @fin X -->.
 *
 * Uso (desde la raíz del proyecto):
 *   docker compose exec web php build/layout.php            aplica los cambios
 *   docker compose exec web php build/layout.php --check    solo comprueba (sale con 1 si hay páginas desactualizadas)
 *   docker compose exec web php build/layout.php --version=20261201   fija la versión de caché (por defecto, fecha y hora)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
$opciones = getopt('', ['check', 'version:']);
$soloComprobar = isset($opciones['check']);
$version = $opciones['version'] ?? date('YmdHi');
if (!preg_match('/^\d{6,14}$/', $version)) {
    fwrite(STDERR, "La versión debe ser numérica (por ejemplo 202612011030).\n");
    exit(2);
}

$plantilla = file_get_contents($raiz . '/partials/layout.html');
if ($plantilla === false) {
    fwrite(STDERR, "No se encuentra partials/layout.html\n");
    exit(2);
}
if (!preg_match_all('/<!-- @bloque ([a-z]+) -->\n(.*?)<!-- @fin bloque -->/s', $plantilla, $m, PREG_SET_ORDER)) {
    fwrite(STDERR, "partials/layout.html no contiene bloques <!-- @bloque nombre --> … <!-- @fin bloque -->\n");
    exit(2);
}
$bloques = [];
foreach ($m as $b) {
    $bloques[$b[1]] = $b[2];
}

/** Ruta pública de una página: index.html es "/". */
function url_pagina(string $archivo): string
{
    return $archivo === 'index.html' ? '/' : '/' . $archivo;
}

/** Prepara un bloque para una página concreta. */
function renderizar(string $bloque, string $archivo, string $version): string
{
    // Contenido condicional: <!-- @si pagina.html --> … <!-- @fin si -->
    $bloque = preg_replace_callback(
        '/[ \t]*<!-- @si ([a-z0-9.-]+) -->\n(.*?)[ \t]*<!-- @fin si -->\n/s',
        function ($c) use ($archivo) { return $c[1] === $archivo ? $c[2] : ''; },
        $bloque
    );

    $url = preg_quote(url_pagina($archivo), '/');
    $bloque = preg_replace('/<a href="' . $url . '" class="gd-quicknav-pill">/', '<a href="' . url_pagina($archivo) . '" class="gd-quicknav-pill active" aria-current="page">', $bloque);
    $bloque = preg_replace('/<li><a href="' . $url . '">/', '<li><a href="' . url_pagina($archivo) . '" class="active" aria-current="page">', $bloque);

    return str_replace('{{version}}', $version, $bloque);
}

$cambiadas = [];
$sinMarcas = [];
foreach (glob($raiz . '/*.html') as $ruta) {
    $archivo = basename($ruta);
    $html = file_get_contents($ruta);
    if (strpos($html, '<!-- @inicio ') === false) {
        $sinMarcas[] = $archivo;
        continue;
    }

    $nuevo = $html;
    foreach ($bloques as $nombre => $bloque) {
        $contenido = renderizar($bloque, $archivo, $version);
        $nuevo = preg_replace_callback(
            '/(<!-- @inicio ' . $nombre . ' [^\n]*-->\n).*?([ \t]*<!-- @fin ' . $nombre . ' -->)/s',
            function ($c) use ($contenido) { return $c[1] . $contenido . $c[2]; },
            $nuevo,
            -1,
            $veces
        );
        if ($veces !== 1) {
            fwrite(STDERR, "$archivo: la marca del bloque '$nombre' aparece $veces veces (debe aparecer 1).\n");
            exit(2);
        }
    }

    if ($soloComprobar) {
        // Se compara sin las versiones: una página no está desactualizada solo por la fecha.
        $sinVersion = function ($s) { return preg_replace('/\?v=\d+/', '?v=', $s); };
        if ($sinVersion($nuevo) !== $sinVersion($html)) {
            $cambiadas[] = $archivo;
        }
        continue;
    }

    // Cada ejecución renueva la versión de caché en todas las páginas, para que los
    // navegadores descarguen también los CSS, JS o iconos que se hayan cambiado.
    $nuevo = preg_replace('/\?v=\d+/', '?v=' . $version, $nuevo);
    if ($nuevo !== $html) {
        $cambiadas[] = $archivo;
        file_put_contents($ruta, $nuevo);
    }
}

if ($soloComprobar) {
    if ($cambiadas) {
        echo "Páginas desactualizadas respecto a partials/layout.html:\n  " . implode("\n  ", $cambiadas) . "\nEjecuta: docker compose exec web php build/layout.php\n";
        exit(1);
    }
    echo "Todas las páginas están al día.\n";
    exit(0);
}

echo $cambiadas
    ? "Actualizadas (versión $version):\n  " . implode("\n  ", $cambiadas) . "\n"
    : "No había nada que actualizar.\n";
if ($sinMarcas) {
    echo "Sin marcas, no se tocan: " . implode(', ', $sinMarcas) . "\n";
}
