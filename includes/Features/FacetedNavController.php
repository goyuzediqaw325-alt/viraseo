<?php
/**
 * Feature 6: Faceted Navigation Crawl Budget Controller
 *
 * Problem: WooCommerce product filtering generates infinite URL combinations:
 * - ?min_price=100&max_price=500&pa_color=red&pa_size=xl&orderby=price
 * - Each combination is a unique URL that Googlebot tries to crawl
 * - This wastes crawl budget and creates duplicate/thin content
 *
 * Solution:
 * - If more than N filter parameters are present → inject noindex,nofollow meta
 * - Admin defines which params are "filter params" vs "safe params"
 * - Fires at `wp_head` (priority 1) for minimal performance impact
 * - Also adds X-Robots-Tag header via `template_redirect` for non-HTML responses
 *
 * Performance Notes:
 * - Uses $_GET parsing (no DB queries on frontend)
 * - Settings cached via WordPress options API
 * - Fires ONLY on product archive/taxonomy pages (not single products)
 * - Zero impact on pages without query parameters
 *
 * @package AdvancedPersianSEO\Features
 */

namespace APSEO\Features;

defined('ABSPATH') || exit;

use APSEO\Admin\Dashboard;

class FacetedNavController {

    /**
     * Option key for faceted nav settings
     */
    private const SETTINGS_KEY = 'apseo_faceted_nav_settings';

    /**
     * Default settings
     */
    private static array $defaults = [
        'enabled'              => true,
        'max_params_allowed'   => 1,
        'filter_params'        => [
            'min_price', 'max_price', 'orderby', 'filter_color',
            'filter_size', 'filter_brand', 'rating_filter',
            'pa_color', 'pa_size', 'pa_brand', 'pa_material',
            'pa_capacity', 'pa_weight',
        ],
        'safe_params'          => ['product_cat', 'product_tag', 'page', 'paged', 's'],
        'custom_filter_prefix' => 'pa_',
        'apply_to'             => ['product_archive', 'product_taxonomy'],
        'noindex_sorting'      => true,
        'add_canonical'        => true,
        'block_in_robots_txt'  => false,
    ];

    /**
     * Cached settings
     */
    private ?array $settings = null;

    /**
     * Constructor
     */
    public function __construct() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Frontend: Inject noindex meta tag (very early in wp_head)
        add_action('wp_head', [$this, 'maybe_inject_noindex'], 1);

        // Frontend: Add X-Robots-Tag header for crawlers
        add_action('template_redirect', [$this, 'maybe_add_robots_header'], 1);

        // Frontend: Optionally add canonical to clean URL
        add_action('wp_head', [$this, 'maybe_add_canonical'], 2);

        // Admin AJAX
        add_action('wp_ajax_apseo_get_faceted_settings', [$this, 'ajax_get_settings']);
        add_action('wp_ajax_apseo_save_faceted_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_apseo_test_faceted_url', [$this, 'ajax_test_url']);
        add_action('wp_ajax_apseo_get_faceted_stats', [$this, 'ajax_get_stats']);
    }

    /**
     * Get current settings (cached)
     */
    private function get_settings(): array {
        if ($this->settings === null) {
            $saved = get_option(self::SETTINGS_KEY, []);
            $this->settings = wp_parse_args($saved, self::$defaults);
        }
        return $this->settings;
    }

    /**
     * Check if current page is a faceted navigation page that should be noindexed
     *
     * @return bool True if the page should be noindexed
     */
    private function should_noindex(): bool {
        $settings = $this->get_settings();

        // Feature must be enabled
        if (empty($settings['enabled'])) {
            return false;
        }

        // Only apply to product archives and taxonomies
        if (!$this->is_applicable_page()) {
            return false;
        }

        // Count filter parameters in current URL
        $filter_count = $this->count_filter_params();

        // If filter count exceeds threshold → noindex
        return $filter_count > (int) $settings['max_params_allowed'];
    }

    /**
     * Check if current page is applicable (product archive/taxonomy)
     */
    private function is_applicable_page(): bool {
        // Product shop page
        if (is_shop()) {
            return true;
        }

        // Product category/tag archives
        if (is_product_taxonomy()) {
            return true;
        }

        // Custom product taxonomies (attributes)
        if (is_tax() && strpos(get_queried_object()->taxonomy ?? '', 'pa_') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Count the number of "filter" parameters in current URL
     * Ignores "safe" parameters (paged, product_cat, etc.)
     */
    private function count_filter_params(): int {
        $settings = $this->get_settings();
        $safe_params = (array) $settings['safe_params'];
        $filter_params = (array) $settings['filter_params'];
        $prefix = $settings['custom_filter_prefix'] ?? 'pa_';

        $count = 0;

        foreach ($_GET as $key => $value) {
            // Skip safe params
            if (in_array($key, $safe_params, true)) {
                continue;
            }

            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }

            // Count if it's in the known filter list OR starts with custom prefix
            if (in_array($key, $filter_params, true) || strpos($key, $prefix) === 0) {
                $count++;
            }

            // Also count sorting with noindex_sorting enabled
            if ($key === 'orderby' && !empty($settings['noindex_sorting'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * FRONTEND: Inject <meta name="robots" content="noindex, nofollow"> in wp_head
     */
    public function maybe_inject_noindex(): void {
        if (!$this->should_noindex()) {
            return;
        }

        echo '<meta name="robots" content="noindex, nofollow" />' . "\n";

        // Also prevent Rank Math from outputting its own robots tag for this page
        // (Rank Math respects this filter)
        add_filter('rank_math/frontend/robots', function (array $robots): array {
            $robots['index'] = 'noindex';
            $robots['follow'] = 'nofollow';
            return $robots;
        });
    }

    /**
     * FRONTEND: Add X-Robots-Tag HTTP header
     * This ensures crawlers respect noindex even for non-HTML responses
     */
    public function maybe_add_robots_header(): void {
        if (!$this->should_noindex()) {
            return;
        }

        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow', true);
        }
    }

    /**
     * FRONTEND: Add canonical pointing to the clean (unfiltered) URL
     */
    public function maybe_add_canonical(): void {
        $settings = $this->get_settings();

        if (empty($settings['add_canonical']) || !$this->is_applicable_page()) {
            return;
        }

        // Only add canonical if there are filter params
        if ($this->count_filter_params() === 0) {
            return;
        }

        // Build clean canonical URL (remove all filter params, keep safe ones)
        $clean_url = $this->get_clean_url();

        if ($clean_url) {
            // Remove Rank Math/Yoast/WP canonical to avoid duplicates
            remove_action('wp_head', 'rel_canonical');
            remove_action('wp_head', 'wp_oembed_add_discovery_links');

            echo '<link rel="canonical" href="' . esc_url($clean_url) . '" />' . "\n";
        }
    }

    /**
     * Get clean URL without filter parameters
     */
    private function get_clean_url(): string {
        $settings = $this->get_settings();
        $safe_params = (array) $settings['safe_params'];

        // Start with the current URL path
        $url = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $base_url = home_url($url);

        // Keep only safe params
        $keep_params = [];
        foreach ($_GET as $key => $value) {
            if (in_array($key, $safe_params, true) && !empty($value)) {
                $keep_params[$key] = $value;
            }
        }

        if (!empty($keep_params)) {
            $base_url .= '?' . http_build_query($keep_params);
        }

        return $base_url;
    }

    /**
     * AJAX: Get faceted navigation settings
     */
    public function ajax_get_settings(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        $settings = $this->get_settings();

        // Format for display
        $settings['filter_params_text'] = implode("\n", $settings['filter_params']);
        $settings['safe_params_text'] = implode("\n", $settings['safe_params']);

        wp_send_json_success($settings);
    }

    /**
     * AJAX: Save faceted navigation settings
     */
    public function ajax_save_settings(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        $input = $_POST;

        $settings = [
            'enabled'              => !empty($input['enabled']),
            'max_params_allowed'   => max(0, absint($input['max_params_allowed'] ?? 1)),
            'filter_params'        => $this->parse_textarea_to_array($input['filter_params_text'] ?? ''),
            'safe_params'          => $this->parse_textarea_to_array($input['safe_params_text'] ?? ''),
            'custom_filter_prefix' => sanitize_text_field($input['custom_filter_prefix'] ?? 'pa_'),
            'noindex_sorting'      => !empty($input['noindex_sorting']),
            'add_canonical'        => !empty($input['add_canonical']),
            'block_in_robots_txt'  => !empty($input['block_in_robots_txt']),
        ];

        update_option(self::SETTINGS_KEY, $settings);
        $this->settings = null; // Clear cache

        wp_send_json_success([
            'message' => __('تنظیمات فیلتر فست‌شده با موفقیت ذخیره شد.', 'advanced-persian-seo'),
        ]);
    }

    /**
     * AJAX: Test a URL to see if it would be noindexed
     */
    public function ajax_test_url(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        $test_url = esc_url_raw($_POST['test_url'] ?? '');

        if (empty($test_url)) {
            wp_send_json_error(__('آدرس URL وارد نشده.', 'advanced-persian-seo'));
        }

        $settings = $this->get_settings();

        // Parse the URL
        $parsed = wp_parse_url($test_url);
        $query_string = $parsed['query'] ?? '';
        parse_str($query_string, $params);

        // Count filter params
        $safe_params = (array) $settings['safe_params'];
        $filter_params = (array) $settings['filter_params'];
        $prefix = $settings['custom_filter_prefix'] ?? 'pa_';

        $filter_count = 0;
        $detected_filters = [];
        $detected_safe = [];

        foreach ($params as $key => $value) {
            if (in_array($key, $safe_params, true)) {
                $detected_safe[] = $key;
                continue;
            }
            if (in_array($key, $filter_params, true) || strpos($key, $prefix) === 0) {
                $filter_count++;
                $detected_filters[] = $key . '=' . $value;
            }
        }

        $would_noindex = $filter_count > (int) $settings['max_params_allowed'];

        wp_send_json_success([
            'url'              => $test_url,
            'filter_count'     => $filter_count,
            'max_allowed'      => (int) $settings['max_params_allowed'],
            'would_noindex'    => $would_noindex,
            'detected_filters' => $detected_filters,
            'detected_safe'    => $detected_safe,
            'result_label'     => $would_noindex
                ? __('⛔ این URL با noindex,nofollow علامت‌گذاری می‌شود.', 'advanced-persian-seo')
                : __('✅ این URL ایندکس‌پذیر باقی می‌ماند.', 'advanced-persian-seo'),
        ]);
    }

    /**
     * AJAX: Get crawl budget stats (how many URLs would be blocked)
     */
    public function ajax_get_stats(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        $settings = $this->get_settings();

        // Get WooCommerce product attribute taxonomies
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        $attributes_list = [];
        foreach ($attribute_taxonomies as $attr) {
            $attributes_list[] = [
                'slug'  => 'pa_' . $attr->attribute_name,
                'label' => $attr->attribute_label,
                'type'  => $attr->attribute_type,
                'terms' => wp_count_terms(['taxonomy' => 'pa_' . $attr->attribute_name]),
            ];
        }

        // Calculate theoretical URL explosion
        $total_combinations = 1;
        foreach ($attributes_list as $attr) {
            $term_count = max(1, (int) $attr['terms']);
            $total_combinations *= ($term_count + 1); // +1 for "not selected"
        }

        // Add price filter combinations (rough estimate)
        $total_combinations *= 10; // price ranges

        wp_send_json_success([
            'enabled'             => $settings['enabled'],
            'max_params'          => $settings['max_params_allowed'],
            'filter_params_count' => count($settings['filter_params']),
            'attributes'          => $attributes_list,
            'theoretical_urls'    => number_format($total_combinations),
            'protected_estimate'  => number_format(max(0, $total_combinations - count($attributes_list) * 2)),
            'message'             => sprintf(
                __('بدون این محافظ، گوگل‌بات ممکن است %s آدرس تکراری را کراول کند.', 'advanced-persian-seo'),
                number_format($total_combinations)
            ),
        ]);
    }

    /**
     * Parse textarea (one item per line) to array
     */
    private function parse_textarea_to_array(string $text): array {
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $lines = array_map('sanitize_text_field', $lines);
        return array_values(array_filter($lines));
    }
}
