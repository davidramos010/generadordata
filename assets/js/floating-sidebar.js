// ULTRA SIMPLE - No complex logic, just basic initialization
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar products if container exists
    const sidebarProductsContainer = document.getElementById('sidebar-products-list');
    if (sidebarProductsContainer && typeof ProductBannerUtils !== 'undefined') {
        sidebarProductsContainer.innerHTML = ProductBannerUtils.createSidebarProducts(SIDEBAR_PRODUCTS_CONFIG);
    }
});
