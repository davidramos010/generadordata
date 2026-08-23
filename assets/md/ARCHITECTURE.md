# Arquitectura y flujo de datos

## Componentes

```
Navegador (index.html / api.html)
   │  assets/js/functions_api.js, custom.js, functions.js
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

## Estructura de `assets/`

| Carpeta | Contenido |
|---|---|
| `assets/css/` | Bootstrap + estilos del tema TemplateMo + `custom.css` (estilos propios). |
| `assets/js/` | jQuery, Bootstrap bundle, y JS propio: `functions.js` (validadores), `functions_api.js` (generador/página API), `custom.js`, `floating-sidebar.js`, `product-banner.js`. |
| `assets/img/`, `assets/webfonts/` | Recursos estáticos del tema. |
| `assets/md/` | Documentación del proyecto (este archivo y los relacionados). |
| `assets/config.php` | Configuración centralizada de credenciales (ver [CONFIGURATION.md](CONFIGURATION.md)). |

## Despliegue

Ver [`README-Docker.md`](../../README-Docker.md): `Dockerfile` (PHP 8.1 + Apache), `docker-compose.yml`
(servicios `web` + `db` MySQL), `init.sql` (crea la BD/tabla del contador).
