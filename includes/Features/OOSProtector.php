<?php
/**
 * Feature 5: Smart Out-Of-Stock (OOS) Traffic Protector
 *
 * Architecture:
 * - Hooks into WooCommerce product stock status changes
 * - Checks GSC traffic data for affected product
 * - If product HAS organic traffic: Keep 200 OK, inject "Alternative Products" block
 * - If product has NO traffic AND is discontinued: Auto-301 redirect to category
 *
 * Performance:
 * - Only fires on product pages (not archives/shop)
 * - Uses cached GSC data from custom table (no API calls on frontend)
 * - Redirect logic fires at `template_redirect` (before any output)
 * - Alternative block injects via `woocommerce_before_single_product`
 *
 * @package AdvancedPersianSEO\Features
 */

namespace APSEO\Features;

defined('ABSPATH') || exit;

use APSEO\Admin\Dashboard;
use APSEO\Utils\JalaliDate;
use APSEO\Utils\PersianText;

class OOSProtector {

    /**
     * Minimum monthly impressions to consider a product as "has traffic"
     */
    private const TRAFFIC_THRESHOLD = 5;

    /**
     * Meta key for marking a product as "discontinued"
     */
    private const DISCONTINUED_META = '_apseo_discontinued';

    /**
     * Meta key for storing redirect override
     */
    private const REDIRECT_META = '_apseo_oos_redirect_url';

    /**
     * Constructor
     */
    public function __construct() {
        // Only load WooCommerce features if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Frontend hooks - performance critical
        add_action('template_redirect', [$this, 'handle_oos_redirect'], 5);
        add_action('woocommerce_before_single_product', [$this, 'inject_alternatives_block'], 15);

        // Backend hooks - stock status change detection
        add_action('woocommerce_product_set_stock_status', [$this, 'on_stock_status_change'], 10, 3);
        add_action('woocommerce_variation_set_stock_status', [$this, 'on_stock_status_change'], 10, 3);

        // Admin AJAX
        add_action('wp_ajax_apseo_get_oos_products', [$this, 'ajax_get_oos_products']);
        add_action('wp_ajax_apseo_set_discontinued', [$this, 'ajax_set_discontinued']);
        add_action('wp_ajax_apseo_set_oos_redirect', [$this, 'ajax_set_redirect']);
        add_action('wp_ajax_apseo_get_oos_stats', [$this, 'ajax_get_stats']);

        // Product meta box
        add_action('woocommerce_product_options_inventory_product_data', [$this, 'render_product_meta_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta_fields']);
    }

    /**
     * FRONTEND: Handle 301 redirect for discontinued products with no traffic
     * Fires BEFORE any output (template_redirect priority 5)
     */
    public function handle_oos_redirect(): void {
        if (!is_product()) {
            return;
        }

        global $post;
        $product = wc_get_product($post->ID);

        if (!$product || $product->get_stock_status() !== 'outofstock') {
            return;
        }

        // Check if marked as discontinued
        $is_discontinued = get_post_meta($post->ID, self::DISCONTINUED_META, true);
        if ($is_discontinued !== 'yes') {
            return;
        }

        // Check if product has organic traffic
        if ($this->product_has_traffic($post->ID)) {
            return; // Has traffic - don't redirect, show alternatives instead
        }

        // Determine redirect target
        $redirect_url = $this->get_redirect_url($post->ID, $product);

        if ($redirect_url) {
            // Log the redirect
            $this->log_redirect($post->ID, $redirect_url);

            wp_redirect($redirect_url, 301);
            exit;
        }
    }

    /**
     * FRONTEND: Inject alternative products block for OOS products WITH traffic
     * Uses woocommerce_before_single_product for optimal placement
     */
    public function inject_alternatives_block(): void {
        global $post;
        $product = wc_get_product($post->ID);

        if (!$product || $product->get_stock_status() !== 'outofstock') {
            return;
        }

        // Only show block if product HAS traffic (we want to save these conversions)
        if (!$this->product_has_traffic($post->ID)) {
            return;
        }

        // Get alternative products
        $alternatives = $this->get_alternative_products($product);

        if (empty($alternatives)) {
            return;
        }

        // Render the block
        include APSEO_PLUGIN_DIR . 'templates/partials/oos-alternatives.php';
    }


    /**
     * Check if a product has organic traffic based on GSC data
     *
     * @param int $post_id Product post ID
     * @return bool True if product has traffic above threshold
     */
    private function product_has_traffic(int $post_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'apseo_gsc_keywords';

        // Check impressions in last 30 days
        $impressions = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(impressions), 0)
             FROM {$table}
             WHERE post_id = %d
               AND date_recorded >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $post_id
        ));

        return (int) $impressions >= self::TRAFFIC_THRESHOLD;
    }

    /**
     * Get redirect URL for a discontinued product
     * Priority: Custom redirect URL > Primary category > Shop page
     */
    private function get_redirect_url(int $post_id, \WC_Product $product): string {
        // 1. Check for custom redirect URL
        $custom_redirect = get_post_meta($post_id, self::REDIRECT_META, true);
        if (!empty($custom_redirect)) {
            return esc_url($custom_redirect);
        }

        // 2. Get primary product category
        $terms = get_the_terms($post_id, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            // Prefer the deepest category (most specific)
            usort($terms, function ($a, $b) {
                return $b->parent - $a->parent;
            });
            return get_term_link($terms[0]);
        }

        // 3. Fallback to shop page
        return wc_get_page_permalink('shop');
    }

    /**
     * Get alternative/related products for OOS product
     *
     * Strategy:
     * 1. Same category + in-stock products
     * 2. Sorted by similarity (shared tags/attributes)
     * 3. Limited to 4 products
     */
    private function get_alternative_products(\WC_Product $product): array {
        $product_id = $product->get_id();

        // Get product categories
        $cat_ids = $product->get_category_ids();
        if (empty($cat_ids)) {
            return [];
        }

        // Query in-stock products from same categories
        $args = [
            'status'      => 'publish',
            'stock_status' => 'instock',
            'category'    => $cat_ids,
            'exclude'     => [$product_id],
            'limit'       => 8,
            'orderby'     => 'popularity',
            'order'       => 'DESC',
        ];

        $alternatives = wc_get_products($args);

        // Score by shared tags/attributes
        $product_tags = $product->get_tag_ids();
        $scored = [];

        foreach ($alternatives as $alt) {
            $score = 0;
            $alt_tags = $alt->get_tag_ids();
            $score += count(array_intersect($product_tags, $alt_tags)) * 2;

            // Price proximity bonus
            $price_diff = abs($product->get_price() - $alt->get_price());
            $max_price = max($product->get_price(), 1);
            if ($price_diff / $max_price < 0.3) {
                $score += 3;
            }

            $scored[] = ['product' => $alt, 'score' => $score];
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['score'] - $a['score']);

        return array_slice(array_column($scored, 'product'), 0, 4);
    }

    /**
     * Hook: When product stock status changes
     */
    public function on_stock_status_change(int $product_id, string $stock_status, \WC_Product $product): void {
        global $wpdb;
        $table = $wpdb->prefix . 'apseo_oos_log';

        if ($stock_status === 'outofstock') {
            // Log the OOS event
            $has_traffic = $this->product_has_traffic($product_id);

            $wpdb->replace($table, [
                'product_id'      => $product_id,
                'product_title'   => $product->get_name(),
                'stock_status'    => 'outofstock',
                'has_traffic'     => $has_traffic ? 1 : 0,
                'action_taken'    => $has_traffic ? 'show_alternatives' : 'pending_review',
                'redirect_url'    => null,
                'detected_at'     => current_time('mysql'),
            ]);
        } elseif ($stock_status === 'instock') {
            // Product back in stock - remove from OOS log
            $wpdb->delete($table, ['product_id' => $product_id], ['%d']);

            // Remove discontinued flag if set
            delete_post_meta($product_id, self::DISCONTINUED_META);
        }
    }

    /**
     * Log a redirect that was executed
     */
    private function log_redirect(int $product_id, string $redirect_url): void {
        global $wpdb;
        $table = $wpdb->prefix . 'apseo_oos_log';

        $wpdb->update($table, [
            'action_taken' => 'redirected_301',
            'redirect_url' => $redirect_url,
        ], ['product_id' => $product_id]);
    }


    /**
     * Render product meta box fields in WooCommerce Inventory tab
     */
    public function render_product_meta_fields(): void {
        global $post;

        echo '<div class="options_group" style="direction:rtl; text-align:right;">';

        woocommerce_wp_checkbox([
            'id'          => self::DISCONTINUED_META,
            'label'       => __('محصول منقضی شده', 'advanced-persian-seo'),
            'description' => __('در صورت عدم ترافیک ارگانیک، ریدایرکت ۳۰۱ خودکار اعمال می‌شود.', 'advanced-persian-seo'),
        ]);

        woocommerce_wp_text_input([
            'id'          => self::REDIRECT_META,
            'label'       => __('آدرس ریدایرکت سفارشی', 'advanced-persian-seo'),
            'description' => __('اختیاری: اگر خالی باشد، به دسته‌بندی اصلی ریدایرکت می‌شود.', 'advanced-persian-seo'),
            'placeholder' => 'https://',
            'type'        => 'url',
        ]);

        echo '</div>';
    }

    /**
     * Save product meta fields
     */
    public function save_product_meta_fields(int $post_id): void {
        $discontinued = isset($_POST[self::DISCONTINUED_META]) ? 'yes' : 'no';
        update_post_meta($post_id, self::DISCONTINUED_META, $discontinued);

        if (isset($_POST[self::REDIRECT_META])) {
            $url = esc_url_raw($_POST[self::REDIRECT_META]);
            update_post_meta($post_id, self::REDIRECT_META, $url);
        }
    }

    /**
     * AJAX: Get OOS products list for admin dashboard
     */
    public function ajax_get_oos_products(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'apseo_oos_log';

        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        $filter = sanitize_text_field($_POST['filter'] ?? 'all');

        $where = "WHERE 1=1";
        $params = [];

        if ($filter === 'has_traffic') {
            $where .= " AND has_traffic = 1";
        } elseif ($filter === 'no_traffic') {
            $where .= " AND has_traffic = 0";
        } elseif ($filter === 'redirected') {
            $where .= " AND action_taken = 'redirected_301'";
        }

        $params[] = $per_page;
        $params[] = $offset;

        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY detected_at DESC LIMIT %d OFFSET %d",
            ...$params
        ));

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");

        $formatted = array_map(function ($item) {
            $action_labels = [
                'show_alternatives' => __('نمایش جایگزین‌ها', 'advanced-persian-seo'),
                'redirected_301'    => __('ریدایرکت ۳۰۱ شده', 'advanced-persian-seo'),
                'pending_review'    => __('نیاز به بررسی', 'advanced-persian-seo'),
            ];

            return [
                'product_id'   => $item->product_id,
                'title'        => $item->product_title,
                'edit_url'     => get_edit_post_link($item->product_id, 'raw'),
                'view_url'     => get_permalink($item->product_id),
                'has_traffic'  => (bool) $item->has_traffic,
                'traffic_label' => $item->has_traffic
                    ? __('دارای ترافیک ✓', 'advanced-persian-seo')
                    : __('بدون ترافیک ✗', 'advanced-persian-seo'),
                'action'       => $item->action_taken,
                'action_label' => $action_labels[$item->action_taken] ?? $item->action_taken,
                'redirect_url' => $item->redirect_url,
                'detected_at'  => JalaliDate::format($item->detected_at, 'relative'),
            ];
        }, $products);

        wp_send_json_success([
            'products' => $formatted,
            'total'    => (int) $total,
            'pages'    => ceil($total / $per_page),
        ]);
    }

    /**
     * AJAX: Mark product as discontinued
     */
    public function ajax_set_discontinued(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        $product_id = absint($_POST['product_id'] ?? 0);
        $discontinued = sanitize_text_field($_POST['discontinued'] ?? 'yes');

        if (!$product_id) {
            wp_send_json_error(__('شناسه محصول نامعتبر.', 'advanced-persian-seo'));
        }

        update_post_meta($product_id, self::DISCONTINUED_META, $discontinued);

        wp_send_json_success([
            'message' => $discontinued === 'yes'
                ? __('محصول به عنوان منقضی علامت‌گذاری شد.', 'advanced-persian-seo')
                : __('علامت منقضی برداشته شد.', 'advanced-persian-seo'),
        ]);
    }

    /**
     * AJAX: Set custom redirect URL for OOS product
     */
    public function ajax_set_redirect(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        $product_id = absint($_POST['product_id'] ?? 0);
        $redirect_url = esc_url_raw($_POST['redirect_url'] ?? '');

        if (!$product_id) {
            wp_send_json_error(__('شناسه نامعتبر.', 'advanced-persian-seo'));
        }

        update_post_meta($product_id, self::REDIRECT_META, $redirect_url);

        wp_send_json_success([
            'message' => __('آدرس ریدایرکت ذخیره شد.', 'advanced-persian-seo'),
        ]);
    }

    /**
     * AJAX: Get OOS statistics
     */
    public function ajax_get_stats(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'apseo_oos_log';

        $stats = [
            'total_oos'       => PersianText::format_number($wpdb->get_var("SELECT COUNT(*) FROM {$table}")),
            'with_traffic'    => PersianText::format_number($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE has_traffic = 1")),
            'without_traffic' => PersianText::format_number($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE has_traffic = 0")),
            'redirected'      => PersianText::format_number($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE action_taken = 'redirected_301'")),
            'pending'         => PersianText::format_number($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE action_taken = 'pending_review'")),
        ];

        wp_send_json_success($stats);
    }
}
