# Guía de Product Banner - Sistema Genérico

## 📁 Archivos del Sistema

### Archivos CSS
- `assets/css/product-banner.css` - Estilos genéricos para todos los banners
- `assets/css/custom.css` - Estilos personalizados adicionales

### Archivos JavaScript
- `assets/js/product-banner.js` - Clases y funciones genéricas
- `assets/js/product-config.js` - **CONFIGURACIÓN DE PRODUCTOS** (modificar aquí)

## �� Cómo Cambiar Productos

### 1. Banner Principal (Superior)

Edita el archivo `assets/js/product-config.js` y modifica la variable `MAIN_BANNER_CONFIG`:

```javascript
const MAIN_BANNER_CONFIG = {
    productImage: 'URL_DE_LA_IMAGEN',
    productTitle: 'TÍTULO DEL PRODUCTO',
    productDescription: 'Descripción del producto',
    productFeatures: '✓ Característica 1 ✓ Característica 2 ✓ Característica 3',
    currentPrice: '€299,99',
    originalPrice: '€399,99',
    discountPercentage: '-25%',
    affiliateLink: 'TU_ENLACE_DE_AFILIADO',
    buyButtonText: 'Comprar en',
    storeName: 'Amazon',
    fallbackIcon: 'fas fa-smartwatch', // Icono si no carga la imagen
    fallbackText: 'Producto'
};
```

### 2. Productos de la Barra Lateral

Modifica el array `SIDEBAR_PRODUCTS_CONFIG` en el mismo archivo:

```javascript
const SIDEBAR_PRODUCTS_CONFIG = [
    {
        productImage: 'URL_IMAGEN_1',
        productTitle: 'Producto 1',
        currentPrice: '€159,00',
        originalPrice: '€199,00',
        savings: 'Ahorras €40,00',
        affiliateLink: 'ENLACE_AFILIADO_1'
    },
    {
        productImage: 'URL_IMAGEN_2',
        productTitle: 'Producto 2',
        currentPrice: '€89,99',
        originalPrice: '',
        savings: '',
        affiliateLink: 'ENLACE_AFILIADO_2'
    }
    // Agregar más productos según necesites
];
```

### 3. Badge de la Barra Lateral

Modifica la variable `BADGE_CONFIG`:

```javascript
const BADGE_CONFIG = {
    text: 'Ofertas Amazon', // Cambiar el texto
    style: 'sidebar-badge'
};
```

## 🎨 Personalización de Estilos

### Colores del Banner Principal
Edita `assets/css/product-banner.css` y modifica la clase `.product-banner-container`:

```css
.product-banner-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); /* Cambiar colores */
    /* ... resto de estilos ... */
}
```

### Colores de Precios
```css
.product-banner-current-price {
    color: #FFD700; /* Color del precio actual */
}

.product-banner-original-price {
    opacity: 0.7; /* Opacidad del precio tachado */
}

.product-banner-discount-badge {
    background: #FF4444; /* Color del badge de descuento */
}
```

## 📱 Responsive Design

El sistema incluye estilos responsive automáticos. Para ajustar:

```css
@media (max-width: 768px) {
    .product-banner-container {
        flex-direction: column !important;
        /* Ajustes para móvil */
    }
}
```

## 🔄 Proceso de Cambio Rápido

1. **Abrir** `assets/js/product-config.js`
2. **Modificar** las variables de configuración
3. **Guardar** el archivo
4. **Recargar** la página web

## ✨ Características del Sistema

- ✅ **Completamente genérico** - Solo cambiar configuración
- ✅ **Responsive** - Se adapta a móviles automáticamente
- ✅ **Efectos hover** - Animaciones y transiciones
- ✅ **Fallback de imágenes** - Iconos si no cargan las imágenes
- ✅ **Enlaces de afiliado** - Preserva todos los enlaces
- ✅ **Fácil mantenimiento** - Un solo archivo para cambiar productos

## 🚀 Ejemplo de Cambio Completo

Para cambiar de smartwatch a laptop:

1. Cambiar `productImage` por imagen de laptop
2. Cambiar `productTitle` por "Laptop Gaming XYZ"
3. Cambiar `productDescription` por descripción de laptop
4. Cambiar `productFeatures` por características de laptop
5. Ajustar precios según el producto
6. Cambiar `affiliateLink` por enlace de la laptop
7. Cambiar `fallbackIcon` por `fas fa-laptop`
8. Cambiar `fallbackText` por "Laptop"

¡Listo! El banner se actualizará automáticamente.
