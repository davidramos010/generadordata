/**
 * Product Banner System - Generic and Reusable
 * Author: AdminKeys Develop
 * Version: 1.0
 */

// Main Product Banner Class
class ProductBanner {
    constructor(config) {
        this.config = {
            productImage: '',
            productTitle: '',
            productDescription: '',
            productFeatures: '',
            currentPrice: '',
            originalPrice: '',
            discountPercentage: '',
            affiliateLink: '',
            buyButtonText: 'Comprar',
            storeName: 'Amazon',
            fallbackIcon: 'fas fa-shopping-cart',
            fallbackText: 'Producto',
            ...config
        };
        
        this.init();
    }
    
    init() {
        this.createBanner();
    }
    
    createBanner() {
        const bannerHTML = `
            <div class="product-banner-container">
                <div class="product-banner-content">
                    <div class="product-banner-image-section">
                        <img src="${this.config.productImage}" 
                             alt="${this.config.productTitle}" 
                             class="product-banner-image"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="product-banner-fallback" style="display: none;">
                            <i class="${this.config.fallbackIcon}"></i>
                            <span>${this.config.fallbackText}</span>
                        </div>
                    </div>
                    <div class="product-banner-info-section">
                        <h3 class="product-banner-title">${this.config.productTitle}</h3>
                        <p class="product-banner-description">${this.config.productDescription}</p>
                        <div class="product-banner-features">${this.config.productFeatures}</div>
                        <div class="product-banner-price-section">
                            ${this.config.originalPrice ? `<span class="product-banner-original-price">${this.config.originalPrice}</span>` : ''}
                            <span class="product-banner-current-price">${this.config.currentPrice}</span>
                            ${this.config.discountPercentage ? `<span class="product-banner-discount">${this.config.discountPercentage}</span>` : ''}
                        </div>
                        <a href="${this.config.affiliateLink}" target="_blank" class="product-banner-button">
                            ${this.config.buyButtonText} ${this.config.storeName}
                        </a>
                    </div>
                </div>
            </div>
        `;
        
        return bannerHTML;
    }
}

// Sidebar Product Class - WITH LINKS (blue underlines)
class SidebarProduct {
    constructor(config) {
        this.config = {
            productImage: '',
            productTitle: '',
            currentPrice: '',
            originalPrice: '',
            savings: '',
            affiliateLink: '',
            ...config
        };
    }
    
    createProduct() {
        // Use normal <a> tags to restore blue underlines
        const productHTML = `
            <div class="sidebar-product-container">
                <a href="${this.config.affiliateLink}" target="_blank" class="product-banner-link">
                    <img src="${this.config.productImage}" 
                         alt="${this.config.productTitle}" 
                         class="sidebar-product-image">
                    ${this.config.currentPrice || this.config.originalPrice ? `
                        <div class="sidebar-product-price-section">
                            ${this.config.originalPrice ? `<span class="sidebar-product-original-price">${this.config.originalPrice}</span>` : ''}
                            ${this.config.currentPrice ? `<span class="sidebar-product-current-price">${this.config.currentPrice}</span>` : ''}
                            ${this.config.savings ? `<span class="sidebar-product-savings">${this.config.savings}</span>` : ''}
                        </div>
                    ` : ''}
                </a>
            </div>
        `;
        
        return productHTML;
    }
}

// Utility functions
const ProductBannerUtils = {
    // Create main banner
    createMainBanner: function(config) {
        const banner = new ProductBanner(config);
        return banner.createBanner();
    },
    
    // Create sidebar product
    createSidebarProduct: function(config) {
        const product = new SidebarProduct(config);
        return product.createProduct();
    },
    
    // Create multiple sidebar products
    createSidebarProducts: function(products) {
        return products.map(product => this.createSidebarProduct(product)).join('');
    },
    
    // Initialize all banners
    init: function() {
        console.log('Product Banner system initialized');
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    ProductBannerUtils.init();
});
