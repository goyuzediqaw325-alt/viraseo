<?php
namespace ViraSEO\Admin;
defined('ABSPATH') || exit;

class Dashboard {
    const SLUG = 'viraseo';
    const OPT = 'viraseo_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'menus']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_viraseo_test_n8n', [$this, 'ajax_test_n8n']);
    }

    public function menus(): void {
        $s = self::SLUG;
        add_menu_page('ویرا سئو', 'ویرا سئو', 'manage_options', $s, [$this,'page'], 'dashicons-chart-area', 30);
        add_submenu_page($s,'داشبورد','داشبورد','manage_options',$s,[$this,'page']);
        add_submenu_page($s,'سرچ کنسول 🟢','سرچ کنسول','manage_options',$s.'-gsc',[$this,'page_gsc']);
        add_submenu_page($s,'تحلیل SERP 🔵','تحلیل SERP','manage_options',$s.'-serp',[$this,'page_serp']);
        add_submenu_page($s,'لینک‌سازی 🟢','لینک‌سازی','manage_options',$s.'-links',[$this,'page_links']);
        add_submenu_page($s,'بک‌لینک CRM 🟢','بک‌لینک','manage_options',$s.'-backlinks',[$this,'page_backlinks']);
        add_submenu_page($s,'پیش‌بینی ترافیک 🟢','پیش‌بینی','manage_options',$s.'-forecast',[$this,'page_forecast']);
        add_submenu_page($s,'کشف کلمات 🔵','کشف کلمات','manage_options',$s.'-discovery',[$this,'page_discovery']);
        if (class_exists('WooCommerce'))
            add_submenu_page($s,'ووکامرس 🟢','ووکامرس','manage_woocommerce',$s.'-woo',[$this,'page_woo']);
        add_submenu_page($s,'ورکفلو n8n 🔵','ورکفلو n8n','manage_options',$s.'-workflows',[$this,'page_workflows']);
        add_submenu_page($s,'تنظیمات','⚙️ تنظیمات','manage_options',$s.'-settings',[$this,'page_settings']);
    }

    public function assets(string $hook): void {
        if (strpos($hook, self::SLUG) === false) return;
        wp_enqueue_style('viraseo-admin', VIRASEO_URL.'assets/css/admin.css', [], VIRASEO_VERSION);
        wp_enqueue_script('viraseo-admin', VIRASEO_URL.'assets/js/admin.js', ['jquery'], VIRASEO_VERSION, true);
        wp_localize_script('viraseo-admin', 'VS', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('viraseo_nonce'),
            'rest' => rest_url('viraseo/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'url' => VIRASEO_URL,
        ]);
    }

    public function register_settings(): void {
        register_setting('viraseo_opts', self::OPT, ['sanitize_callback' => [$this, 'sanitize']]);
    }

    public function sanitize(array $i): array {
        return [
            'n8n_url' => esc_url_raw(rtrim($i['n8n_url'] ?? '', '/')),
            'n8n_secret' => sanitize_text_field($i['n8n_secret'] ?? ''),
            'oauth_proxy_url' => esc_url_raw(rtrim($i['oauth_proxy_url'] ?? '', '/')),
            'gsc_client_id' => sanitize_text_field($i['gsc_client_id'] ?? ''),
            'gsc_client_secret' => sanitize_text_field($i['gsc_client_secret'] ?? ''),
            'striking_min' => max(1, absint($i['striking_min'] ?? 11)),
            'striking_max' => max(1, absint($i['striking_max'] ?? 20)),
            'min_impressions' => absint($i['min_impressions'] ?? 10),
            'remove_data' => !empty($i['remove_data']),
        ];
    }

    public static function get(string $key = ''): mixed {
        $s = wp_parse_args(get_option(self::OPT, []), [
            'n8n_url'=>'','n8n_secret'=>'','oauth_proxy_url'=>'',
            'gsc_client_id'=>'','gsc_client_secret'=>'',
            'striking_min'=>11,'striking_max'=>20,'min_impressions'=>10,'remove_data'=>false,
        ]);
        return $key ? ($s[$key] ?? null) : $s;
    }

    public function ajax_test_n8n(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        $url = self::get('n8n_url');
        if (!$url) wp_send_json_error('آدرس n8n تنظیم نشده.');
        $r = wp_remote_post($url.'/webhook/viraseo-health', [
            'timeout'=>10,
            'headers'=>['Content-Type'=>'application/json','X-ViraSEO-Secret'=>self::get('n8n_secret')],
            'body'=>wp_json_encode(['action'=>'ping']),
        ]);
        if (is_wp_error($r)) wp_send_json_error($r->get_error_message());
        $code = wp_remote_retrieve_response_code($r);
        $code >= 200 && $code < 300
            ? wp_send_json_success('اتصال برقرار ✓')
            : wp_send_json_error("پاسخ HTTP {$code}");
    }

    // Page renderers
    public function page(): void { include VIRASEO_DIR.'templates/admin/dashboard.php'; }
    public function page_gsc(): void { include VIRASEO_DIR.'templates/admin/gsc.php'; }
    public function page_serp(): void { include VIRASEO_DIR.'templates/admin/serp.php'; }
    public function page_links(): void { include VIRASEO_DIR.'templates/admin/links.php'; }
    public function page_backlinks(): void { include VIRASEO_DIR.'templates/admin/backlinks.php'; }
    public function page_forecast(): void { include VIRASEO_DIR.'templates/admin/forecast.php'; }
    public function page_discovery(): void { include VIRASEO_DIR.'templates/admin/discovery.php'; }
    public function page_woo(): void { include VIRASEO_DIR.'templates/admin/woo.php'; }
    public function page_workflows(): void { include VIRASEO_DIR.'templates/admin/workflows.php'; }
    public function page_settings(): void { include VIRASEO_DIR.'templates/admin/settings.php'; }
}
