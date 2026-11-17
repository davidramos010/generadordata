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

## Cascarón común de las 8 páginas

Todas las páginas comparten el mismo HTML de cabecera móvil, menú lateral (`#gdSidebar`,
Bootstrap Offcanvas responsive `offcanvas-lg`), barra de píldoras `gd-quicknav` con las 5
herramientas y footer. Está duplicado en cada archivo (sitio estático, sin includes): al tocarlo
hay que replicar el cambio en las 8 páginas. Cada página marca su enlace con `class="active"
aria-current="page"` en el menú y en la quicknav.

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
