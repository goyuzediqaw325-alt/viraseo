<?php
/**
 * Dashboard V2 - Registers new admin menu pages for Features 5-9
 *
 * Extends the base Dashboard class by adding:
 * - WooCommerce SEO submenu (OOS Protector, Faceted Nav)
 * - Traffic Forecasting submenu
 * - Keyword Discovery submenu
 *
 * @package AdvancedPersianSEO\Admin
 */

namespace APSEO\Admin;

defined('ABSPATH') || exit;

class DashboardV2 {

    /**
     * Parent menu slug (from base Dashboard)
     */
    private const PARENT_SLUG = 'apseo-dashboard';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menus'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_v2_assets']);
    }

    /**
     * Register new submenu pages
     */
    public function register_menus(): void {
        // WooCommerce SEO section
        if (class_exists('WooCommerce')) {
            add_submenu_page(
                self::PARENT_SLUG,
                __('سئو ووکامرس', 'advanced-persian-seo'),
                __('🛒 سئو ووکامرس', 'advanced-persian-seo'),
                'manage_woocommerce',
                'apseo-woocommerce',
                [$this, 'render_woocommerce_page']
            );
        }

        // Traffic Forecaster
        add_submenu_page(
            self::PARENT_SLUG,
            __('پیش‌بینی ترافیک', 'advanced-persian-seo'),
            __('📈 پیش‌بینی ترافیک', 'advanced-persian-seo'),
            'manage_options',
            'apseo-traffic-forecast',
            [$this, 'render_forecast_page']
        );

        // Keyword Discovery
        add_submenu_page(
            self::PARENT_SLUG,
            __('کشف کلمات کلیدی', 'advanced-persian-seo'),
            __('🔍 کشف کلمات', 'advanced-persian-seo'),
            'manage_options',
            'apseo-keyword-discovery',
            [$this, 'render_discovery_page']
        );
    }

    /**
     * Enqueue V2-specific assets
     */
    public function enqueue_v2_assets(string $hook): void {
        $v2_pages = [
            'apseo-woocommerce',
            'apseo-traffic-forecast',
            'apseo-keyword-discovery',
        ];

        $is_v2_page = false;
        foreach ($v2_pages as $slug) {
            if (strpos($hook, $slug) !== false) {
                $is_v2_page = true;
                break;
            }
        }

        if (!$is_v2_page) {
            return;
        }

        // V2 admin JS (extends base admin.js)
        wp_enqueue_script(
            'apseo-admin-v2-js',
            APSEO_PLUGIN_URL . 'assets/js/admin-v2.js',
            ['jquery', 'wp-util', 'apseo-admin-js'],
            APSEO_VERSION,
            true
        );
    }

    /**
     * Render WooCommerce SEO page (OOS + Faceted Nav)
     */
    public function render_woocommerce_page(): void {
        include APSEO_PLUGIN_DIR . 'templates/admin/woocommerce-seo.php';
    }

    /**
     * Render Traffic Forecaster page
     */
    public function render_forecast_page(): void {
        include APSEO_PLUGIN_DIR . 'templates/admin/traffic-forecast.php';
    }

    /**
     * Render Keyword Discovery page
     */
    public function render_discovery_page(): void {
        include APSEO_PLUGIN_DIR . 'templates/admin/keyword-discovery.php';
    }
}
