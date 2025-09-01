/**
 * Product Configuration - Easy to modify for different products
 * Author: AdminKeys Develop
 * Version: 1.0
 */

// Main banner product configuration
const MAIN_BANNER_CONFIG = {
    productImage: 'https://m.media-amazon.com/images/I/61Cib9ek4EL._AC_SL1500_.jpg',
    productTitle: 'HUAWEI Smartwatch con Cristal de Zafiro',
    productDescription: 'Smartwatch deportivo con rendimiento premium, monitoreo de salud avanzado y resistencia al agua',
    productFeatures: '✓ GPS integrado ✓ Resistente al agua ✓ Batería de larga duración',
    currentPrice: '€139,00',
    originalPrice: '€169,00',
    discountPercentage: '-18%',
    affiliateLink: 'https://amzn.to/4neyz3I',
    buyButtonText: 'Comprar en',
    storeName: 'Amazon',
    fallbackIcon: 'fas fa-smartwatch',
    fallbackText: 'Huawei'
};

// Sidebar products configuration
const SIDEBAR_PRODUCTS_CONFIG = [
    {
        productImage: 'https://m.media-amazon.com/images/I/71tsfMzD-VL._AC_SX679_.jpg',
        productTitle: 'Lenovo Tablet Tab M11',
        currentPrice: '€175,00',
        originalPrice: '€249,00',
        savings: 'Ahorras €74,00',
        affiliateLink: 'https://amzn.to/4mJYAba'
    },
    {
        productImage: 'https://m.media-amazon.com/images/I/71yjjVup+OL._AC_SX679_.jpg',
        productTitle: 'Producto Amazon',
        currentPrice: '',
        originalPrice: '',
        savings: '',
        affiliateLink: 'https://amzn.to/4mJYAba'
    },
    {
        productImage: 'https://m.media-amazon.com/images/I/71DGjCJb2fL._AC_SX569_.jpg',
        productTitle: 'Producto Amazon',
        currentPrice: '€175,00',
        originalPrice: '€249,00',
        savings: 'Ahorras €74,00',
        discountPercentage: '-30%',
        affiliateLink: 'https://amzn.to/4mJYAba'
    }
];

// Badge configuration
const BADGE_CONFIG = {
    text: 'Ofertas Amazon',
    style: 'sidebar-badge'
};

// Export configurations (for use in other scripts)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        MAIN_BANNER_CONFIG,
        SIDEBAR_PRODUCTS_CONFIG,
        BADGE_CONFIG
    };
}
