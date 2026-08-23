# Configuración: `assets/config.php`

Todas las credenciales y rutas usadas por el backend PHP (`api_proxy.php`, `counter.php`) están
centralizadas en [`assets/config.php`](../config.php). No hay que tocar esos dos archivos para
cambiar una credencial.

## Cómo funciona

Cada constante usa **primero la variable de entorno del mismo nombre** si existe, y si no, cae en
un valor por defecto escrito en el propio archivo:

```php
define('API_BASE_URL', getenv('API_BASE_URL') ?: 'http://172.20.0.1:8001/api');
```

Esto permite dos formas de configurar el proyecto sin duplicar lógica:

| Entorno | Cómo se configura |
|---|---|
| **Local** (sin Docker) | Edita directamente los valores por defecto en `assets/config.php`. |
| **Docker** | Define/edita las variables de entorno del servicio `web` en `docker-compose.yml`. Tienen prioridad sobre los valores por defecto del archivo. |

## Constantes disponibles

| Constante | Usada en | Variable de entorno | Valor por defecto |
|---|---|---|---|
| `API_BASE_URL` | `api_proxy.php` | `API_BASE_URL` | `http://172.20.0.1:8001/api` |
| `LOGIN_EMAIL` | `api_proxy.php` | `LOGIN_EMAIL` | `test@example.com` |
| `LOGIN_PASS` | `api_proxy.php` | `LOGIN_PASS` | `password` |
| `DB_HOST` | `counter.php` | `DB_HOST` | `localhost` |
| `DB_USERNAME` | `counter.php` | `DB_USERNAME` | `userBD` |
| `DB_PASSWORD` | `counter.php` | `DB_PASSWORD` | `passBD` |
| `DB_NAME` | `counter.php` | `DB_NAME` | `admin_BD` |

## Para añadir una nueva credencial/ruta

1. Añade la constante en `assets/config.php` siguiendo el mismo patrón (`getenv(...) ?: 'default'`).
2. Si el valor debe poder configurarse en Docker, añade también la variable de entorno
   correspondiente en `docker-compose.yml` (servicio `web`).
3. Usa la constante en el PHP que la necesite, tras `require_once __DIR__ . '/assets/config.php';`
   (o la ruta relativa equivalente si el archivo no está en la raíz del proyecto).

## Nota de seguridad

`assets/config.php` contiene credenciales por defecto pensadas para desarrollo local. Si este
proyecto se despliega en un entorno real, esos valores por defecto deben cambiarse (o mejor,
definirse solo por variable de entorno) y el archivo no debería exponer credenciales de
producción en texto plano dentro del repositorio.
