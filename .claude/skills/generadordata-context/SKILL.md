---
name: generadordata-context
description: Use when working on the GeneradorData repo (generación/validación de DNI, NIF, NIE, CIF) - da contexto de arquitectura, lógica de negocio y dónde vive la configuración, sin tener que re-explorar el repo desde cero.
---

# Contexto: GeneradorData

Sitio estático (HTML/Bootstrap, tema TemplateMo) para **generar y validar documentos de
identidad españoles**: DNI, NIF, NIE, CIF.

## Lógica de negocio — dos flujos independientes

1. **Generación** (llamada a backend): `assets/js/functions_api.js` → `POST api_proxy.php`
   → `api_proxy.php` hace login + llama a una **API Laravel externa** (`generate-dni`,
   `generate-nif`, `generate-nie`, `generate-cif-by-type`) → devuelve `{ data: [...] }`.
2. **Validación** (100% client-side, sin backend): `assets/js/functions.js` →
   `validateSpanishID(id)` implementa el algoritmo de letra de control español.

`counter.php` es una tercera pieza sin relación con lo anterior: contador de visitas en MySQL,
heredado de la plantilla original.

## Configuración — un único punto: `assets/config.php`

Todas las credenciales (API Laravel: `API_BASE_URL`, `LOGIN_EMAIL`, `LOGIN_PASS`; MySQL:
`DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME`) están en `assets/config.php` como constantes
`define('X', getenv('X') ?: 'default')`. **Nunca hardcodear credenciales de nuevo en
`api_proxy.php` o `counter.php`** — añadir la constante en `config.php` y, si aplica, la env var
en `docker-compose.yml` (servicio `web`). Detalle: `assets/md/CONFIGURATION.md`.

## Documentación completa

- `assets/md/PROJECT-OVERVIEW.md` — qué es el proyecto, páginas, lógica de negocio.
- `assets/md/ARCHITECTURE.md` — diagrama de flujo de datos y estructura de `assets/`.
- `assets/md/CONFIGURATION.md` — cómo funciona `assets/config.php` y cómo extenderlo.
- `README-Docker.md` — despliegue con Docker (Dockerfile, docker-compose.yml, init.sql).

Léelos antes de asumir cómo funciona algo que no está en este resumen.

## Nota de seguridad conocida

El archivo `generadordata` en la raíz del repo contiene una clave privada SSH en texto plano,
versionada en git. Está pendiente de rotar/purgar; no reutilizar ni distribuir esa clave.
