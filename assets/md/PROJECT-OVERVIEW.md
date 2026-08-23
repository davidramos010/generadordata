# GeneradorData — Visión general del proyecto

## Qué es

GeneradorData es un sitio web para **generar y validar documentos de identidad españoles**:
DNI, NIF, NIE y CIF. Es un proyecto de plantilla estática (HTML/Bootstrap, basado en un tema
TemplateMo) al que se le han añadido páginas y componentes propios para esta funcionalidad.

## Páginas principales

| Página | Contenido |
|---|---|
| `index.html` | Página principal: generadores y validadores de DNI/NIF/NIE/CIF (widgets unificados, ver memoria de sesión sobre diseño de widgets). |
| `api.html` | Documentación/demo de la API pública del proyecto. |
| `about.html`, `contact.html`, `rgpd.html` | Páginas informativas/legales del sitio. |
| `shop.html`, `shop-single.html` | Páginas heredadas de la plantilla original (no forman parte de la lógica de generación de documentos). |

## Lógica de negocio

Hay dos funcionalidades independientes:

1. **Generación de documentos aleatorios válidos** (DNI, NIF, NIE, CIF): el frontend pide a
   `api_proxy.php` que genere N documentos de un tipo dado. `api_proxy.php` no genera nada él
   mismo: hace de intermediario hacia una **API Laravel externa** (ver
   [ARCHITECTURE.md](ARCHITECTURE.md)).
2. **Validación de documentos** (¿es válido este NIF/NIE/CIF?): se resuelve **en el propio
   navegador**, sin llamar al backend, mediante la función `validateSpanishID` en
   `assets/js/functions.js` (algoritmo de letra de control español).

## Otras piezas

- **Contador de visitas** (`counter.php`): incrementa y lee un contador por página en MySQL.
  No tiene relación con la generación/validación de documentos; es una utilidad heredada de la
  plantilla original.
- **Configuración**: todas las credenciales (API Laravel y MySQL) están centralizadas en
  [`assets/config.php`](../config.php) — ver [CONFIGURATION.md](CONFIGURATION.md).

## Para profundizar

- Flujo de datos y estructura de carpetas: [ARCHITECTURE.md](ARCHITECTURE.md)
- Cómo configurar credenciales/rutas (local y Docker): [CONFIGURATION.md](CONFIGURATION.md)
- Despliegue con Docker: [`README-Docker.md`](../../README-Docker.md)
