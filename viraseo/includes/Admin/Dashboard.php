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
        add_submenu_page($s,'مانیتورینگ کلمات 🟢','مانیتورینگ کلمات','manage_options',$s.'-rank',[$this,'page_rank']);
        add_submenu_page($s,'فرصت‌های سئو 🟢','فرصت‌های سئو','manage_options',$s.'-opps',[$this,'page_opps']);
        add_submenu_page($s,'سئوی ۲۰۲۶ ✨','سئوی ۲۰۲۶','manage_options',$s.'-modern',[$this,'page_modern']);
        add_submenu_page($s,'سرعت و CWV ⚡','⚡ سرعت سایت','manage_options',$s.'-cwv',[$this,'page_cwv']);
        add_submenu_page($s,'هم‌نوع‌خواری ⚔️','⚔️ کنیبال','manage_options',$s.'-cannibal',[$this,'page_cannibal']);
        add_submenu_page($s,'🤖 ابزارهای AI','🤖 ابزارهای AI','manage_options',$s.'-ai',[$this,'page_ai']);
        add_submenu_page($s,'وضعیت ایندکس 🟢','وضعیت ایندکس','manage_options',$s.'-index',[$this,'page_index']);
        add_submenu_page($s,'سلامت خزش 🕷️','🕷️ سلامت خزش','manage_options',$s.'-crawl',[$this,'page_crawl']);
        add_submenu_page($s,'کلمات هدف 🟢','کلمات هدف','manage_options',$s.'-targets',[$this,'page_targets']);
        add_submenu_page($s,'استراتژی کلمات 🟢','استراتژی کلمات','manage_options',$s.'-strategy',[$this,'page_strategy']);
        add_submenu_page($s,'لینک‌سازی 🟢','لینک‌سازی','manage_options',$s.'-links',[$this,'page_links']);
        add_submenu_page($s,'بک‌لینک CRM 🟢','بک‌لینک','manage_options',$s.'-backlinks',[$this,'page_backlinks']);
        add_submenu_page($s,'پیش‌بینی ترافیک 🟢','پیش‌بینی','manage_options',$s.'-forecast',[$this,'page_forecast']);
        add_submenu_page($s,'کشف کلمات 🔵','کشف کلمات','manage_options',$s.'-discovery',[$this,'page_discovery']);
        if (class_exists('WooCommerce'))
            add_submenu_page($s,'ووکامرس 🟢','ووکامرس','manage_woocommerce',$s.'-woo',[$this,'page_woo']);
        add_submenu_page($s,'ورکفلو n8n 🔵','ورکفلو n8n','manage_options',$s.'-workflows',[$this,'page_workflows']);
        add_submenu_page($s,'🔧 تشخیص مشکلات','🔧 تشخیص','manage_options',$s.'-diagnostics',[$this,'page_diagnostics']);
        add_submenu_page($s,'تنظیمات','⚙️ تنظیمات','manage_options',$s.'-settings',[$this,'page_settings']);
    }

    public function assets(string $hook): void {
        if (strpos($hook, self::SLUG) === false) return;

        // Register handles (needed for dependencies + localize)
        wp_register_style('viraseo-admin', false, [], VIRASEO_VERSION);
        wp_enqueue_style('viraseo-admin');

        wp_register_script('viraseo-admin', false, ['jquery'], VIRASEO_VERSION, true);
        wp_enqueue_script('viraseo-admin');

        // Localize config FIRST (so VS is defined before our inline JS runs)
        wp_localize_script('viraseo-admin', 'VS', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('viraseo_nonce'),
            'rest' => rest_url('viraseo/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'url' => VIRASEO_URL,
            'aiEnabled' => \ViraSEO\Api\AiClient::is_enabled(),
        ]);

        // INLINE the CSS + JS to bypass servers that 403-block static plugin files.
        // Falls back to file enqueue if reading fails.
        $css_file = VIRASEO_DIR . 'assets/css/admin.css';
        $js_file  = VIRASEO_DIR . 'assets/js/admin.js';

        if (is_readable($css_file)) {
            wp_add_inline_style('viraseo-admin', file_get_contents($css_file));
        } else {
            wp_enqueue_style('viraseo-admin-file', VIRASEO_URL.'assets/css/admin.css', [], VIRASEO_VERSION);
        }

        if (is_readable($js_file)) {
            wp_add_inline_script('viraseo-admin', file_get_contents($js_file));
        } else {
            wp_enqueue_script('viraseo-admin-file', VIRASEO_URL.'assets/js/admin.js', ['jquery'], VIRASEO_VERSION, true);
        }
    }

    public function register_settings(): void {
        register_setting('viraseo_opts', self::OPT, ['sanitize_callback' => [$this, 'sanitize']]);
    }

    public function sanitize(array $i): array {
        return [
            'n8n_url' => esc_url_raw(rtrim($i['n8n_url'] ?? '', '/')),
            'n8n_secret' => sanitize_text_field($i['n8n_secret'] ?? ''),
            'serper_api_key' => sanitize_text_field($i['serper_api_key'] ?? ''),
            'psi_api_key' => sanitize_text_field($i['psi_api_key'] ?? ''),
            'ai_enabled' => !empty($i['ai_enabled']),
            'openrouter_key' => sanitize_text_field($i['openrouter_key'] ?? ''),
            'ai_proxy_url' => esc_url_raw(rtrim($i['ai_proxy_url'] ?? '', '/')),
            'ai_curl_proxy' => sanitize_text_field($i['ai_curl_proxy'] ?? ''),
            'ai_model' => sanitize_text_field($i['ai_model'] ?? 'openai/gpt-4o-mini'),
            'ai_max_tokens' => max(1000, min(16000, absint($i['ai_max_tokens'] ?? 4000))),
            'ai_token_mode' => in_array($i['ai_token_mode'] ?? 'auto', ['auto','manual'], true) ? $i['ai_token_mode'] : 'auto',
            'allowed_post_types' => array_filter(array_map('sanitize_text_field', (array)($i['allowed_post_types'] ?? []))),
            'psi_use_proxy' => !empty($i['psi_use_proxy']),
            'oauth_proxy_url' => esc_url_raw(rtrim($i['oauth_proxy_url'] ?? '', '/')),
            'gsc_client_id' => sanitize_text_field($i['gsc_client_id'] ?? ''),
            'gsc_client_secret' => sanitize_text_field($i['gsc_client_secret'] ?? ''),
            'striking_min' => max(1, absint($i['striking_min'] ?? 11)),
            'striking_max' => max(1, absint($i['striking_max'] ?? 20)),
            'min_impressions' => absint($i['min_impressions'] ?? 10),
            'rank_max_pages' => min(10, max(1, absint($i['rank_max_pages'] ?? 3))),
            'rank_auto_enabled' => !empty($i['rank_auto_enabled']),
            'rank_alert_email' => !empty($i['rank_alert_email']),
            'rank_alert_threshold' => max(1, absint($i['rank_alert_threshold'] ?? 3)),
            'remove_data' => !empty($i['remove_data']),
        ];
    }

    public static function get(string $key = ''): mixed {
        $s = wp_parse_args(get_option(self::OPT, []), [
            'n8n_url'=>'','n8n_secret'=>'','serper_api_key'=>'','ai_enabled'=>false,'openrouter_key'=>'','ai_proxy_url'=>'','ai_curl_proxy'=>'','ai_model'=>'openai/gpt-4o-mini','oauth_proxy_url'=>'',
            'gsc_client_id'=>'','gsc_client_secret'=>'',
            'psi_api_key'=>'',
            'striking_min'=>11,'striking_max'=>20,'min_impressions'=>10,'rank_max_pages'=>3,'rank_auto_enabled'=>false,'rank_alert_email'=>false,'rank_alert_threshold'=>3,'remove_data'=>false,
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
    public function page_rank(): void { include VIRASEO_DIR.'templates/admin/rank-monitor.php'; }
    public function page_opps(): void { include VIRASEO_DIR.'templates/admin/opportunities.php'; }
    public function page_modern(): void { include VIRASEO_DIR.'templates/admin/modern.php'; }
    public function page_cwv(): void { include VIRASEO_DIR.'templates/admin/cwv.php'; }
    public function page_cannibal(): void { include VIRASEO_DIR.'templates/admin/cannibalization.php'; }    public function page_ai(): void { include VIRASEO_DIR.'templates/admin/ai-tools.php'; }
    public function page_index(): void { include VIRASEO_DIR.'templates/admin/index-status.php'; }
    public function page_crawl(): void { include VIRASEO_DIR.'templates/admin/crawl.php'; }
    public function page_targets(): void { include VIRASEO_DIR.'templates/admin/targets.php'; }
    public function page_strategy(): void { include VIRASEO_DIR.'templates/admin/strategy.php'; }
    public function page_links(): void { include VIRASEO_DIR.'templates/admin/links.php'; }
    public function page_backlinks(): void { include VIRASEO_DIR.'templates/admin/backlinks.php'; }
    public function page_forecast(): void { include VIRASEO_DIR.'templates/admin/forecast.php'; }
    public function page_discovery(): void { include VIRASEO_DIR.'templates/admin/discovery.php'; }
    public function page_woo(): void { include VIRASEO_DIR.'templates/admin/woo.php'; }
    public function page_workflows(): void { include VIRASEO_DIR.'templates/admin/workflows.php'; }
    public function page_diagnostics(): void { include VIRASEO_DIR.'templates/admin/diagnostics.php'; }
    public function page_settings(): void { include VIRASEO_DIR.'templates/admin/settings.php'; }
}
