# Arquitectura y flujo de datos

## Componentes

```
Navegador (index.html, api.html, generador-*.html, about/contact/rgpd.html)
   │  assets/js/theme.js (tema), counter.js (visitas), functions.js / functions_api.js
   │
   ├── Validación de documentos (NIF/NIE/CIF) ──► se resuelve 100% en el navegador
   │                                               (assets/js/functions.js → validateSpanishID)
   │
   └── Generación de documentos ──► POST api_proxy.php
                                        │
                                        │  (PHP + cURL, usa assets/config.php)
                                        ▼
                                  API Laravel externa
                                  (login + /generate-*)

counter.php ──► MySQL (tabla `conter`, usa assets/config.php)
```

## Flujo de generación de documentos

1. El usuario elige tipo (DNI/NIF/NIE/CIF), cantidad y, si es CIF, el tipo de CIF (A-K).
2. El frontend (`assets/js/functions_api.js`, evento `#btnGenerar`) hace `POST` a
   `api_proxy.php` con `{ tipo, cantidad, tipoCif }`.
3. `api_proxy.php`:
   - Valida `tipo` (`DNI|NIF|NIE|CIF`) y `cantidad` (1-20).
   - Hace login contra la API Laravel (`apiLogin()`) usando `LOGIN_EMAIL`/`LOGIN_PASS` de
     `assets/config.php`, y obtiene un token Bearer.
   - Mapea el tipo al endpoint real (`generate-dni`, `generate-nif`, `generate-nie`,
     `generate-cif-by-type`) y llama a la API (`apiGenerate()`) con ese token.
   - Devuelve `{ data: [...] }` al frontend, o un `{ error }` con el código HTTP correspondiente.
4. El frontend añade los documentos generados al historial en pantalla y permite exportarlos a CSV.

`api.html` usa esta misma llamada a `api_proxy.php` para generar, pero además construye —
únicamente en el navegador, sin llamar a nadie— un ejemplo de comando `curl` equivalente para
quien quiera consumir la API Laravel directamente. Esa URL de ejemplo (`API_BASE_URL` dentro de
`functions_api.js`) es solo texto ilustrativo, no una llamada real.

## Flujo de validación de documentos

Totalmente client-side: `validateSpanishID(id)` en `assets/js/functions.js` implementa el
algoritmo de la letra de control española y no depende de `api_proxy.php` ni de la API Laravel.

## Contador de visitas

`counter.php` es independiente de lo anterior. Se conecta directamente a MySQL con `mysqli`
usando las constantes de `assets/config.php`, incrementa `conter.num` para la página indicada y
devuelve el nuevo valor.

## Conversor Base64 ↔ PDF

```
base64-pdf.html + assets/js/base64_pdf.js
   │  fetch POST (FormData) con cabecera X-Requested-With: XMLHttpRequest
   ▼
base64controller.php   (POST only · mismo origen · límite 20 peticiones/10 min por IP · post_max_size)
   │  action=decode → gd_b64_to_pdf($_POST['base64'])
   │  action=encode → gd_pdf_to_b64($_FILES['pdf'])
   ▼
assets/functions/base64_pdf.php   (solo funciones; protegido con GD_APP y .htaccess)
```

- Todo se procesa en memoria: no se escribe ningún archivo del usuario en disco.
- Validación de PDF (`gd_pdf_inspect`): cabecera `%PDF-` en el primer KB, `finfo` = `application/pdf`,
  `%%EOF` en el último KB. Detecta contenido activo (`/JavaScript`, `/Launch`, `/EmbeddedFile`…): la
  página no muestra vista previa de esos PDFs.
- Los mensajes de error salen de una tabla cerrada (`gd_b64_error_message`) y nunca repiten la entrada.
- Límites: PDF de 10 MB, Base64 de 14 M caracteres. PHP necesita `upload_max_filesize=11M`,
  `post_max_size=16M`, `memory_limit=256M` y `display_errors=Off` (`docker/php/uploads.ini` en Docker,
  `.user.ini` o panel de Plesk en producción).

## Decodificador / codificador JWT

```
decodificador-jwt.html + assets/js/jwt.js
   │  decodificación visual en el navegador (Base64URL + JSON, sin claves)
   │  fetch POST solo en: decode (claims, al pegar), verify y encode
   ▼
jwtcontroller.php   (mismos guardas que base64: POST · mismo origen · X-Requested-With · 60 peticiones/10 min por IP)
   ▼
assets/functions/jwt.php
```

- Algoritmos: verifica y firma HS256/384/512, RS256/384/512, ES256/384/512 (OpenSSL) y EdDSA (sodium).
  PS256/384/512 solo se decodifican (`openssl_sign` no admite RSA-PSS). `alg: none` nunca se verifica.
- Contra la confusión de algoritmos: un HS* solo se verifica con secreto y un RS/ES/EdDSA solo con clave
  pública; la curva tiene que corresponder al algoritmo y las claves RSA de firma han de tener ≥ 2048 bits.
- Claves: PEM (SPKI, PKCS#1, PKCS#8, certificado), JWK (RSA, EC, OKP) o URL JWKS / OpenID configuration.
- JWKS (`gd_jwks_fetch`): solo https:443, bloquea IPs privadas, locales y de metadatos, fija la IP
  validada con `CURLOPT_RESOLVE` (anti DNS rebinding), sin redirecciones, 5 s y 100 KB como máximo.
- Sin botón «Compartir»: meter un token en la URL lo filtraría al historial, los logs y Analytics.

## Utilidades comunes de los controladores

`assets/functions/security.php`: `gd_fail`, `gd_json_headers`, `gd_respond`, `gd_ajax_guard` (POST,
mismo origen, `X-Requested-With`, `post_max_size`) y `gd_rate_limit` (con un cubo por controlador).
Cualquier controlador AJAX nuevo debe empezar por `define('GD_APP', true)`, cargar `security.php` y
pasar por `gd_ajax_guard()` y `gd_rate_limit()`.

## Cascarón común de las páginas

La cabecera móvil, el menú lateral (`#gdSidebar`, Bootstrap Offcanvas `offcanvas-lg`), la barra
de herramientas `gd-quicknav` y el pie se editan en **un solo archivo: `partials/layout.html`**.
Después de cambiarlo (o de cambiar un CSS, JS o icono) se ejecuta:

```bash
docker compose exec web php build/layout.php            # aplica a todas las páginas
docker compose exec web php build/layout.php --check    # solo comprueba; sale con 1 si hay páginas desactualizadas
```

`build/layout.php` (compatible con PHP 7.4) copia cada bloque de `partials/layout.html` entre las
marcas `<!-- @inicio cabecera|quicknav|pie … -->` / `<!-- @fin … -->` de cada `.html` de la raíz,
marca como activo el enlace de esa página (`class="active" aria-current="page"`; en la barra,
`gd-quicknav-pill active`), incluye los fragmentos `<!-- @si pagina.html -->…<!-- @fin si -->` solo
en esa página (el submenú de la API) y renueva la versión de caché `?v=` de todas las páginas.
**No se edita el menú directamente en las páginas**: el script sobrescribe lo que hay entre las marcas.

Por qué así y no con includes en el servidor: producción (Plesk + nginx) sirve los `.html` como
estáticos. Las páginas siguen siendo HTML puro (mismas URLs, menú visible para Google) y no hace
falta configurar nada en el servidor. Para una página nueva, copia una existente (con sus marcas)
y ejecuta el script. `partials/` y `build/` están bloqueados por `.htaccess` y `robots.txt`.

El tema lo fija `<html data-bs-theme="dark|light">` (sistema nativo de Bootstrap 5.3). Un
snippet al inicio de `<body>` lo lee de `localStorage['gd-theme']` antes del primer pintado y
`assets/js/theme.js` lo alterna con los botones `[data-theme-toggle]`. Oscuro por defecto.

## Estructura de `assets/`

| Carpeta | Contenido |
|---|---|
| `assets/css/` | `bootstrap.min.css` (5.3.9), `theme.css` (tokens de color claro/oscuro + variables `--bs-*`), `layout.css` (cascarón: menú, quicknav, hero, banner API, secciones info, footer), `widgets.css` (paneles de los generadores `.gd-card`), `fontawesome.css` (iconos del footer). `templatemo.css` y `custom.css` solo los usan `shop.html`/`shop-single.html` (restos de la plantilla). |
| `assets/js/` | `bootstrap.bundle.min.js` (5.3.9), `theme.js` (tema), `counter.js` (visitas), `functions.js` (generador/validador del index), `functions_api.js` (widgets de api.html y landings). jQuery 1.11, `templatemo.js`, `custom.js`, `floating-sidebar.js`, `product-banner.js` y slick solo los usan las páginas shop. |
| `assets/img/` | `logo.svg` (isotipo en espiral del menú), `icons.svg` (sprite de iconos, se usa con `<use href="assets/img/icons.svg#i-…">`), `logoGeneradorData*.png` (Open Graph / JSON-LD), resto de imágenes. |
| `assets/webfonts/` | Fuentes de FontAwesome. Inter y JetBrains Mono se cargan de Google Fonts. |
| `assets/md/` | Documentación del proyecto (este archivo y los relacionados). |
| `assets/config.php` | Configuración centralizada de credenciales (ver [CONFIGURATION.md](CONFIGURATION.md)). |

## Despliegue

Ver [`README-Docker.md`](../../README-Docker.md): `Dockerfile` (PHP 8.1 + Apache), `docker-compose.yml`
(servicios `web` + `db` MySQL), `init.sql` (crea la BD/tabla del contador).
