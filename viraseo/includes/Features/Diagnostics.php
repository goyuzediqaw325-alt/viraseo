<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Admin\Dashboard;
use ViraSEO\Api\GoogleOAuth;

/**
 * Diagnostics — checks all services and shows status
 */
class Diagnostics {

    public function __construct() {
        add_action('wp_ajax_viraseo_run_diagnostics', [$this, 'ajax_run']);
        add_action('wp_ajax_viraseo_test_n8n_webhook', [$this, 'ajax_test_webhook']);
    }

    /** Run full diagnostics */
    public function ajax_run(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $settings = Dashboard::get();
        $results = [];

        // 1. Database tables
        global $wpdb;
        $tables = ['gsc_keywords','cannibalization','serp_analysis','serp_competitors','internal_links','orphan_pages','link_suggestions','backlinks','disavow','oos_log','keyword_discoveries','keyword_ideas','activity_log'];
        $db_ok = true;
        $db_detail = [];
        foreach ($tables as $t) {
            $full = $wpdb->prefix . 'viraseo_' . $t;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full}'") === $full;
            $count = $exists ? (int)$wpdb->get_var("SELECT COUNT(*) FROM {$full}") : -1;
            $db_detail[] = ['table' => $t, 'exists' => $exists, 'rows' => $count];
            if (!$exists) $db_ok = false;
        }
        $results['database'] = [
            'status' => $db_ok ? 'ok' : 'error',
            'message' => $db_ok ? '✅ همه ۱۳ جدول موجود هستند.' : '❌ بعضی جداول وجود ندارند. افزونه را غیرفعال و دوباره فعال کنید.',
            'tables' => $db_detail,
        ];

        // 2. GSC Connection
        $gsc_token = get_option('viraseo_gsc_token');
        $gsc_connected = !empty($gsc_token['access_token']);
        $gsc_message = $gsc_connected
            ? '✅ اتصال برقرار (متصل از: ' . ($gsc_token['connected_at'] ?? 'نامشخص') . ')'
            : '❌ متصل نیست. از صفحه سرچ کنسول، دکمه «اتصال به گوگل» بزنید.';

        // Test if token actually works
        $gsc_api_ok = false;
        if ($gsc_connected) {
            $test = GoogleOAuth::api('/sites', [], 'GET');
            if (empty($test['error'])) {
                $gsc_api_ok = true;
                $site_count = count($test['siteEntry'] ?? []);
                $gsc_message .= " — {$site_count} سایت در دسترس.";
            } else {
                $gsc_message = '⚠️ توکن معتبر نیست: ' . ($test['message'] ?? 'خطا');
            }
        }

        $results['gsc'] = [
            'status' => $gsc_api_ok ? 'ok' : ($gsc_connected ? 'warning' : 'error'),
            'message' => $gsc_message,
            'proxy_url' => $settings['oauth_proxy_url'] ?: '(تنظیم نشده)',
        ];

        // 3. n8n Connection
        $n8n_url = $settings['n8n_url'];
        $n8n_status = 'error';
        $n8n_message = '❌ آدرس n8n تنظیم نشده.';
        $n8n_webhooks = [];

        if ($n8n_url) {
            // Test health endpoint
            $health = wp_remote_post($n8n_url . '/webhook/viraseo-health', [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json', 'X-ViraSEO-Secret' => $settings['n8n_secret'] ?? ''],
                'body' => wp_json_encode(['action' => 'ping']),
            ]);

            if (is_wp_error($health)) {
                $n8n_message = '❌ خطا در اتصال به n8n: ' . $health->get_error_message();
            } else {
                $code = wp_remote_retrieve_response_code($health);
                if ($code >= 200 && $code < 300) {
                    $n8n_status = 'ok';
                    $n8n_message = '✅ n8n در دسترس است و Health Check پاسخ داد.';
                } elseif ($code === 404) {
                    $n8n_status = 'warning';
                    $n8n_message = '⚠️ n8n در دسترسه ولی ورکفلو Health Check فعال نیست. ورکفلو 03-health-check.json را import و فعال کنید.';
                } else {
                    $n8n_message = "⚠️ n8n پاسخ داد ولی با کد HTTP {$code}.";
                    $n8n_status = 'warning';
                }
            }

            // Test individual webhooks
            $webhooks_to_test = [
                'viraseo-serp-analyze' => 'تحلیل SERP',
                'viraseo-keyword-discover' => 'کشف کلمات کلیدی',
                'viraseo-health' => 'Health Check',
            ];

            foreach ($webhooks_to_test as $path => $label) {
                $test = wp_remote_post($n8n_url . '/webhook/' . $path, [
                    'timeout' => 5,
                    'headers' => ['Content-Type' => 'application/json', 'X-ViraSEO-Secret' => $settings['n8n_secret'] ?? ''],
                    'body' => wp_json_encode(['action' => 'test', 'test' => true]),
                ]);

                $wh_code = is_wp_error($test) ? 0 : wp_remote_retrieve_response_code($test);
                $wh_ok = $wh_code >= 200 && $wh_code < 500 && $wh_code !== 404;

                $n8n_webhooks[] = [
                    'path' => $path,
                    'label' => $label,
                    'status' => $wh_ok ? 'ok' : 'error',
                    'http_code' => $wh_code,
                    'message' => $wh_ok
                        ? "✅ فعال (HTTP {$wh_code})"
                        : ($wh_code === 404
                            ? "❌ یافت نشد (404) — ورکفلو import یا فعال نشده"
                            : "❌ خطا" . (is_wp_error($test) ? ': ' . $test->get_error_message() : " (HTTP {$wh_code})")),
                ];
            }
        }

        $results['n8n'] = [
            'status' => $n8n_status,
            'message' => $n8n_message,
            'url' => $n8n_url ?: '(تنظیم نشده)',
            'secret' => !empty($settings['n8n_secret']) ? '✓ تنظیم شده' : '✗ خالی',
            'webhooks' => $n8n_webhooks,
        ];

        // 4. Data Summary
        $kw_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}viraseo_gsc_keywords");
        $orphan_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}viraseo_orphan_pages WHERE status='orphan'");
        $bl_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}viraseo_backlinks");
        $serp_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}viraseo_serp_analysis");

        $results['data'] = [
            'keywords' => $kw_count,
            'orphans' => $orphan_count,
            'backlinks' => $bl_count,
            'serp_analyses' => $serp_count,
            'last_gsc_sync' => get_option('viraseo_last_gsc_sync', 'هنوز همگام‌سازی نشده'),
            'last_scan' => get_option('viraseo_last_scan', 'هنوز اسکن نشده'),
        ];

        // 5. WordPress Environment
        $results['environment'] = [
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'plugin_version' => VIRASEO_VERSION,
            'site_url' => get_site_url(),
            'rest_url' => rest_url('viraseo/v1/'),
            'woocommerce' => class_exists('WooCommerce') ? '✅ فعال' : '❌ غیرفعال',
            'action_scheduler' => function_exists('as_schedule_recurring_action') ? '✅ فعال' : '⚠️ نصب نشده',
        ];

        wp_send_json_success($results);
    }

    /** Test a specific n8n webhook */
    public function ajax_test_webhook(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        $path = sanitize_text_field($_POST['path'] ?? '');
        if (!$path) wp_send_json_error('مسیر webhook مشخص نشده.');

        $settings = Dashboard::get();
        if (!$settings['n8n_url']) wp_send_json_error('آدرس n8n تنظیم نشده.');

        $url = $settings['n8n_url'] . '/webhook/' . $path;
        $response = wp_remote_post($url, [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-ViraSEO-Secret' => $settings['n8n_secret'] ?? '',
            ],
            'body' => wp_json_encode([
                'action' => 'test',
                'test' => true,
                'site_url' => get_site_url(),
                'timestamp' => current_time('mysql'),
            ]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('❌ خطا: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 404) {
            wp_send_json_error("❌ Webhook '{$path}' یافت نشد (404). ورکفلو مربوطه در n8n فعال نیست.\n\nراه‌حل: فایل JSON مربوطه را از بخش «ورکفلوهای n8n» دانلود و در n8n خود Import کنید، سپس آن را Active کنید.");
        }

        if ($code >= 200 && $code < 300) {
            wp_send_json_success("✅ Webhook پاسخ داد (HTTP {$code}).\n\nپاسخ: " . mb_substr($body, 0, 200));
        }

        wp_send_json_error("⚠️ پاسخ غیرمنتظره (HTTP {$code}): " . mb_substr($body, 0, 200));
    }
}
