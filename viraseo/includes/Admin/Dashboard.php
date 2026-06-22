<?php
namespace ViraSEO\Admin;

class Dashboard {

    const MENU_SLUG = 'viraseo';
    const SETTINGS_KEY = 'viraseo_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_viraseo_test_n8n', [$this, 'ajax_test_connection']);
    }

    /**
     * Register admin menu and submenu pages.
     */
    public function register_menus() {
        add_menu_page(
            'ویراسئو',
            'ویراسئو',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'page_dashboard'],
            'dashicons-chart-area',
            30
        );

        add_submenu_page(
            self::MENU_SLUG,
            'داشبورد',
            'داشبورد',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'page_dashboard']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'سرچ کنسول',
            'سرچ کنسول',
            'manage_options',
            self::MENU_SLUG . '-gsc',
            [$this, 'page_gsc']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'تحلیل SERP',
            'تحلیل SERP',
            'manage_options',
            self::MENU_SLUG . '-serp',
            [$this, 'page_serp']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'لینک‌های داخلی',
            'لینک‌های داخلی',
            'manage_options',
            self::MENU_SLUG . '-links',
            [$this, 'page_links']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'بک‌لینک‌ها',
            'بک‌لینک‌ها',
            'manage_options',
            self::MENU_SLUG . '-backlinks',
            [$this, 'page_backlinks']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'پیش‌بینی ترافیک',
            'پیش‌بینی ترافیک',
            'manage_options',
            self::MENU_SLUG . '-forecast',
            [$this, 'page_forecast']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'کشف کلمات کلیدی',
            'کشف کلمات کلیدی',
            'manage_options',
            self::MENU_SLUG . '-discovery',
            [$this, 'page_discovery']
        );

        // WooCommerce submenu - only if WooCommerce is active
        if (class_exists('WooCommerce')) {
            add_submenu_page(
                self::MENU_SLUG,
                'ووکامرس سئو',
                'ووکامرس سئو',
                'manage_options',
                self::MENU_SLUG . '-woo',
                [$this, 'page_woo']
            );
        }

        add_submenu_page(
            self::MENU_SLUG,
            'تنظیمات',
            'تنظیمات',
            'manage_options',
            self::MENU_SLUG . '-settings',
            [$this, 'page_settings']
        );

        add_submenu_page(
            self::MENU_SLUG,
            'ورکفلوهای n8n',
            'ورکفلوهای n8n',
            'manage_options',
            self::MENU_SLUG . '-workflows',
            [$this, 'page_workflows']
        );
    }

    /**
     * Enqueue admin assets only on ViraSEO pages.
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'viraseo-admin',
            VIRASEO_URL . 'assets/css/admin.css',
            [],
            VIRASEO_VERSION
        );

        wp_enqueue_script(
            'viraseo-admin',
            VIRASEO_URL . 'assets/js/admin.js',
            ['jquery'],
            VIRASEO_VERSION,
            true
        );

        wp_localize_script('viraseo-admin', 'viraseo', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('viraseo_nonce'),
            'strings' => [
                'confirm_delete' => 'آیا مطمئن هستید؟',
                'loading' => 'در حال بارگذاری...',
                'success' => 'عملیات با موفقیت انجام شد.',
                'error' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.',
                'connection_ok' => 'اتصال به n8n برقرار است.',
                'connection_failed' => 'اتصال به n8n برقرار نشد.',
            ],
        ]);
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'viraseo_settings_group',
            self::SETTINGS_KEY,
            ['sanitize_callback' => [$this, 'sanitize_settings']]
        );
    }

    /**
     * Sanitize settings before saving.
     */
    public function sanitize_settings(array $input): array {
        $sanitized = [];

        $sanitized['n8n_webhook_base_url'] = isset($input['n8n_webhook_base_url'])
            ? esc_url_raw(untrailingslashit($input['n8n_webhook_base_url']))
            : '';

        $sanitized['n8n_secret_key'] = isset($input['n8n_secret_key'])
            ? sanitize_text_field($input['n8n_secret_key'])
            : '';

        $sanitized['striking_distance_min'] = isset($input['striking_distance_min'])
            ? absint($input['striking_distance_min'])
            : 4;

        $sanitized['striking_distance_max'] = isset($input['striking_distance_max'])
            ? absint($input['striking_distance_max'])
            : 20;

        $sanitized['min_impressions_threshold'] = isset($input['min_impressions_threshold'])
            ? absint($input['min_impressions_threshold'])
            : 10;

        $sanitized['remove_data_on_uninstall'] = !empty($input['remove_data_on_uninstall']) ? 1 : 0;

        return $sanitized;
    }

    /**
     * Get plugin settings with defaults.
     */
    public static function get_settings(): array {
        $defaults = [
            'n8n_webhook_base_url' => '',
            'n8n_secret_key' => '',
            'striking_distance_min' => 4,
            'striking_distance_max' => 20,
            'min_impressions_threshold' => 10,
            'remove_data_on_uninstall' => 0,
        ];

        $settings = get_option(self::SETTINGS_KEY, []);

        return wp_parse_args($settings, $defaults);
    }

    /**
     * AJAX handler: test n8n connection.
     */
    public function ajax_test_connection() {
        check_ajax_referer('viraseo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $settings = self::get_settings();

        if (empty($settings['n8n_webhook_base_url'])) {
            wp_send_json_error(['message' => 'آدرس webhook تنظیم نشده است.']);
        }

        $url = trailingslashit($settings['n8n_webhook_base_url']) . 'health';

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-ViraSEO-Secret' => $settings['n8n_secret_key'],
            ],
            'body' => wp_json_encode(['action' => 'health_check']),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => 'خطا در اتصال: ' . $response->get_error_message(),
            ]);
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            wp_send_json_success(['message' => 'اتصال به n8n برقرار است.']);
        } else {
            wp_send_json_error([
                'message' => 'پاسخ نامعتبر از n8n. کد وضعیت: ' . $code,
            ]);
        }
    }

    /**
     * Render dashboard page.
     */
    public function page_dashboard() {
        include VIRASEO_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render Google Search Console page.
     */
    public function page_gsc() {
        include VIRASEO_DIR . 'templates/admin/gsc.php';
    }

    /**
     * Render SERP analysis page.
     */
    public function page_serp() {
        include VIRASEO_DIR . 'templates/admin/serp.php';
    }

    /**
     * Render internal links page.
     */
    public function page_links() {
        include VIRASEO_DIR . 'templates/admin/links.php';
    }

    /**
     * Render backlinks page.
     */
    public function page_backlinks() {
        include VIRASEO_DIR . 'templates/admin/backlinks.php';
    }

    /**
     * Render traffic forecast page.
     */
    public function page_forecast() {
        include VIRASEO_DIR . 'templates/admin/forecast.php';
    }

    /**
     * Render keyword discovery page.
     */
    public function page_discovery() {
        include VIRASEO_DIR . 'templates/admin/discovery.php';
    }

    /**
     * Render WooCommerce SEO page.
     */
    public function page_woo() {
        include VIRASEO_DIR . 'templates/admin/woo.php';
    }

    /**
     * Render settings page.
     */
    public function page_settings() {
        include VIRASEO_DIR . 'templates/admin/settings.php';
    }

    /**
     * Render n8n workflows manager page.
     */
    public function page_workflows() {
        include VIRASEO_DIR . 'templates/admin/workflows.php';
    }
}
