<?php
/**
 * Template: Out-of-Stock Alternative Products Block
 *
 * Injected via woocommerce_before_single_product for OOS products with traffic.
 * This template saves conversions by offering users in-stock alternatives.
 *
 * Variables available:
 * - $alternatives: Array of WC_Product objects (max 4)
 * - $product: Current OOS WC_Product
 *
 * @package AdvancedPersianSEO
 */

defined('ABSPATH') || exit;

if (empty($alternatives)) {
    return;
}
?>
<div class="apseo-oos-alternatives-wrapper" dir="rtl" style="margin-bottom: 30px;">
    <div class="apseo-oos-notice">
        <div class="apseo-oos-notice-icon">⚠️</div>
        <div class="apseo-oos-notice-content">
            <h3 class="apseo-oos-title">
                <?php _e('این محصول در حال حاضر ناموجود است', 'advanced-persian-seo'); ?>
            </h3>
            <p class="apseo-oos-subtitle">
                <?php _e('محصولات مشابه و جایگزین زیر موجود هستند:', 'advanced-persian-seo'); ?>
            </p>
        </div>
    </div>

    <div class="apseo-alternatives-grid">
        <?php foreach ($alternatives as $alt_product): ?>
            <div class="apseo-alt-product-card">
                <a href="<?php echo esc_url(get_permalink($alt_product->get_id())); ?>" class="apseo-alt-product-link">
                    <div class="apseo-alt-product-image">
                        <?php echo $alt_product->get_image('woocommerce_thumbnail'); ?>
                    </div>
                    <div class="apseo-alt-product-info">
                        <h4 class="apseo-alt-product-title">
                            <?php echo esc_html($alt_product->get_name()); ?>
                        </h4>
                        <span class="apseo-alt-product-price">
                            <?php echo $alt_product->get_price_html(); ?>
                        </span>
                        <?php if ($alt_product->get_average_rating() > 0): ?>
                            <span class="apseo-alt-product-rating">
                                ⭐ <?php echo number_format($alt_product->get_average_rating(), 1); ?>
                            </span>
                        <?php endif; ?>
                        <span class="apseo-alt-product-stock apseo-in-stock">
                            <?php _e('✓ موجود', 'advanced-persian-seo'); ?>
                        </span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.apseo-oos-alternatives-wrapper {
    background: #fff8f0;
    border: 2px solid #ff9800;
    border-radius: 12px;
    padding: 24px;
    margin: 20px 0 30px;
}
.apseo-oos-notice {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 20px;
}
.apseo-oos-notice-icon {
    font-size: 28px;
    line-height: 1;
}
.apseo-oos-title {
    margin: 0 0 4px;
    font-size: 16px;
    font-weight: 700;
    color: #e65100;
}
.apseo-oos-subtitle {
    margin: 0;
    color: #555;
    font-size: 14px;
}
.apseo-alternatives-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}
.apseo-alt-product-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.apseo-alt-product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.apseo-alt-product-link {
    text-decoration: none;
    color: inherit;
    display: block;
}
.apseo-alt-product-image {
    aspect-ratio: 1;
    overflow: hidden;
}
.apseo-alt-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.apseo-alt-product-info {
    padding: 12px;
    text-align: right;
}
.apseo-alt-product-title {
    margin: 0 0 8px;
    font-size: 13px;
    font-weight: 600;
    color: #333;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.apseo-alt-product-price {
    display: block;
    font-weight: 700;
    color: #2563eb;
    margin-bottom: 4px;
}
.apseo-alt-product-rating {
    font-size: 12px;
    color: #666;
}
.apseo-in-stock {
    display: block;
    font-size: 11px;
    color: #059669;
    font-weight: 600;
    margin-top: 4px;
}
</style>
