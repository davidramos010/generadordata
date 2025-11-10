# Historial de Cambios en `index.html`

## 2025-11-10

### Nuevas Características
- **Validadores de Documentos**: Se han añadido validadores para NIF, NIE y CIF en la página principal (`index.html`).
  - Se crearon nuevas secciones en `index.html` con campos de entrada y botones para la validación.
  - Se implementó la lógica de validación en `assets/js/functions.js`, utilizando una función central `validateSpanishID` que comprueba la validez de los tres tipos de documentos.
  - Se añadieron estilos personalizados en `assets/css/custom.css` para mejorar la apariencia de los nuevos componentes, asegurando que el diseño sea coherente con el resto de la página.
  - Se aplicaron las nuevas clases de CSS a los elementos correspondientes en `index.html`.

### Cambios Específicos
- **`index.html`**:
  - Añadida una nueva fila (`<div class="row">`) con tres columnas para los validadores de NIF, NIE y CIF.
  - Cada columna contiene una tarjeta (`<div class="card">`) con un título, un campo de entrada, un botón de validación y un párrafo para mostrar el resultado.
- **`assets/js/functions.js`**:
  - Añadida la función `validateSpanishID(id)` para la lógica de validación.
  - Añadidos los listeners de eventos para los botones de validación (`#validateNifButton`, `#validateNieButton`, `#validateCifButton`).
- **`assets/css/custom.css`**:
  - Añadidas las clases `.validator-card` y `.validation-result` para estilizar los nuevos componentes.
  - Definidos los colores para los mensajes de éxito (`.text-success`) y error (`.text-danger`).
