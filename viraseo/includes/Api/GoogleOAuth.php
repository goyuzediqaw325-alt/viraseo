<?php
namespace ViraSEO\Api;
defined('ABSPATH') || exit;

use ViraSEO\Admin\Dashboard;

/**
 * Google OAuth2 via Proxy — One-Click GSC Connection
 * 
 * فلو:
 * 1. کاربر "اتصال" می‌زنه
 * 2. ریدایرکت به OAuth Proxy → Google Consent
 * 3. کاربر اجازه می‌ده
 * 4. Proxy توکن می‌گیره → ریدایرکت به سایت کاربر با توکن
 * 5. افزونه توکن ذخیره می‌کنه → تمام!
 * 
 * مثل Rank Math — کاربر هیچ Client ID/Secret نیاز نداره.
 */
class GoogleOAuth {

    private const TOKEN_OPT = 'viraseo_gsc_token';
    private const GSC_API = 'https://www.googleapis.com/webmasters/v3';

    /** Default proxy URL — change this to your deployed Cloudflare Worker */
    private const DEFAULT_PROXY = 'https://viraseo-auth.YOUR-ACCOUNT.workers.dev';

    public function __construct() {
        add_action('wp_ajax_viraseo_gsc_connect', [$this, 'ajax_connect']);
        add_action('wp_ajax_viraseo_gsc_callback', [$this, 'handle_callback']);
        add_action('wp_ajax_viraseo_gsc_disconnect', [$this, 'ajax_disconnect']);
        add_action('wp_ajax_viraseo_gsc_status', [$this, 'ajax_status']);
        add_action('wp_ajax_viraseo_gsc_fetch', [$this, 'ajax_fetch']);
        add_action('wp_ajax_viraseo_gsc_sites', [$this, 'ajax_sites']);
        add_action('wp_ajax_viraseo_gsc_daily', [$this, 'ajax_daily']);
    }

    /** Get OAuth Proxy URL from settings or default */
    private static function proxy_url(): string {
        $custom = Dashboard::get('oauth_proxy_url');
        return !empty($custom) ? rtrim($custom, '/') : self::DEFAULT_PROXY;
    }

    /** WP callback URL that proxy will redirect back to */
    private static function callback_url(): string {
        return admin_url('admin-ajax.php?action=viraseo_gsc_callback');
    }

    /**
     * AJAX: Generate redirect URL and send to browser
     * User clicks "اتصال به گوگل" → JS redirects to this URL
     */
    public function ajax_connect(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $proxy = self::proxy_url();
        if (strpos($proxy, 'YOUR-ACCOUNT') !== false) {
            wp_send_json_error('⚠️ آدرس OAuth Proxy تنظیم نشده. در تنظیمات افزونه، آدرس Worker خود را وارد کنید.');
        }

        // Build the auth URL: Proxy will redirect to Google
        $auth_url = $proxy . '/auth?' . http_build_query([
            'redirect' => self::callback_url(),
        ]);

        wp_send_json_success(['redirect_url' => $auth_url]);
    }

    /**
     * Callback: Proxy redirects here with tokens in URL
     * URL: ?gsc_access_token=xxx&gsc_refresh_token=xxx&gsc_expires_in=3600&gsc_connected=1
     * OR: ?gsc_error=xxx
     */
    public function handle_callback(): void {
        if (!current_user_can('manage_options')) wp_die('Unauthorized', 403);

        $error = sanitize_text_field($_GET['gsc_error'] ?? '');
        if ($error) {
            wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_error=' . urlencode($error)));
            exit;
        }

        $access_token = sanitize_text_field($_GET['gsc_access_token'] ?? '');
        $refresh_token = sanitize_text_field($_GET['gsc_refresh_token'] ?? '');
        $expires_in = absint($_GET['gsc_expires_in'] ?? 3600);

        if (!$access_token) {
            wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_error=no_token'));
            exit;
        }

        // Save tokens!
        update_option(self::TOKEN_OPT, [
            'access_token'  => $access_token,
            'refresh_token' => $refresh_token,
            'expires_at'    => time() + $expires_in,
            'connected_at'  => current_time('mysql'),
        ]);

        wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_connected=1'));
        exit;
    }

    /** Disconnect */
    public function ajax_disconnect(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        delete_option(self::TOKEN_OPT);
        wp_send_json_success(['message' => 'اتصال قطع شد.']);
    }

    /** Status check */
    public function ajax_status(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        $token = get_option(self::TOKEN_OPT);
        wp_send_json_success([
            'connected' => !empty($token['access_token']),
            'connected_at' => $token['connected_at'] ?? '',
        ]);
    }

    /** Get valid access token (auto-refresh via proxy if expired) */
    public static function get_access_token(): ?string {
        $token = get_option(self::TOKEN_OPT);
        if (!$token || empty($token['access_token'])) return null;

        // Still valid?
        if (($token['expires_at'] ?? 0) > time() + 60) {
            return $token['access_token'];
        }

        // Refresh via proxy
        if (empty($token['refresh_token'])) return null;

        $proxy = self::proxy_url();
        $response = wp_remote_post($proxy . '/refresh', [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['refresh_token' => $token['refresh_token']]),
        ]);

        if (is_wp_error($response)) return null;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            // Refresh failed — token revoked
            delete_option(self::TOKEN_OPT);
            return null;
        }

        // Update stored token
        $token['access_token'] = $body['access_token'];
        $token['expires_at'] = time() + ($body['expires_in'] ?? 3600);
        update_option(self::TOKEN_OPT, $token);

        return $token['access_token'];
    }

    /** Make GSC API request */
    public static function api(string $endpoint, array $body = [], string $method = 'POST'): array {
        $access_token = self::get_access_token();
        if (!$access_token) {
            return ['error' => true, 'message' => 'به سرچ کنسول متصل نیستید.'];
        }

        $args = [
            'method' => $method, 'timeout' => 30,
            'headers' => ['Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'application/json'],
        ];
        if ($method === 'POST' && $body) $args['body'] = wp_json_encode($body);

        $response = wp_remote_request(self::GSC_API . $endpoint, $args);
        if (is_wp_error($response)) return ['error' => true, 'message' => $response->get_error_message()];

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 401) { delete_option(self::TOKEN_OPT); return ['error' => true, 'message' => 'توکن منقضی. مجدداً متصل شوید.']; }
        if ($code >= 400) return ['error' => true, 'message' => $data['error']['message'] ?? "HTTP {$code}"];

        return $data ?: [];
    }

    /** URL Inspection API (different base host than webmasters/v3). */
    public static function inspect(string $site, string $url): array {
        $token = self::get_access_token();
        if (!$token) return ['error' => true, 'message' => 'به سرچ کنسول متصل نیستید.'];
        $resp = wp_remote_post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
            'timeout' => 30,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode(['inspectionUrl' => $url, 'siteUrl' => $site, 'languageCode' => 'fa']),
        ]);
        if (is_wp_error($resp)) return ['error' => true, 'message' => $resp->get_error_message()];
        $code = wp_remote_retrieve_response_code($resp);
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code === 401) { delete_option(self::TOKEN_OPT); return ['error' => true, 'message' => 'توکن منقضی. مجدداً متصل شوید.']; }
        if ($code >= 400) return ['error' => true, 'message' => $data['error']['message'] ?? "HTTP {$code}"];
        return $data ?: [];
    }
    public function ajax_sites(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        $r = self::api('/sites', [], 'GET');
        if (!empty($r['error'])) wp_send_json_error($r['message']);
        wp_send_json_success(['sites' => array_map(fn($s) => $s['siteUrl'] ?? '', $r['siteEntry'] ?? [])]);
    }

    /** AJAX: Daily time-series (sorted by date desc) for the timeline view. */
    public function ajax_daily(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        $series = get_option('viraseo_gsc_daily', []);
        if (!is_array($series)) $series = [];
        // Sort by date descending (most recent first), like Search Console
        usort($series, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        $rows = array_map(fn($d) => [
            'date_en'     => $d['date'] ?? '',
            'date'        => \ViraSEO\Utils\JalaliDate::format(($d['date'] ?? '') . ' 00:00:00', 'long'),
            'clicks'      => \ViraSEO\Utils\PersianText::format_number((int)($d['clicks'] ?? 0)),
            'impressions' => \ViraSEO\Utils\PersianText::format_number((int)($d['impressions'] ?? 0)),
            'ctr'         => \ViraSEO\Utils\JalaliDate::to_fa(number_format(($d['ctr'] ?? 0) * 100, 2)) . '%',
            'position'    => \ViraSEO\Utils\JalaliDate::to_fa(number_format($d['position'] ?? 0, 1)),
        ], $series);
        $totals = [
            'clicks'      => array_sum(array_column($series, 'clicks')),
            'impressions' => array_sum(array_column($series, 'impressions')),
        ];
        wp_send_json_success([
            'rows'        => $rows,
            'range_days'  => (int) get_option('viraseo_gsc_range_days', 28),
            'total_clicks'=> \ViraSEO\Utils\PersianText::format_number($totals['clicks']),
            'total_impr'  => \ViraSEO\Utils\PersianText::format_number($totals['impressions']),
        ]);
    }

    /** AJAX: Fetch GSC data */
    public function ajax_fetch(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $site = sanitize_text_field($_POST['site_url'] ?? '');
        if (!$site) $site = get_site_url();
        $days = min(90, max(7, absint($_POST['days'] ?? 28)));

        // Save selected site for future use
        update_option('viraseo_gsc_site', $site);

        // Build query — NO country filter (get all data). dataState 'all' = include fresh/partial data.
        $query_body = [
            'startDate' => date('Y-m-d', strtotime("-{$days} days")),
            'endDate' => date('Y-m-d', strtotime('-2 days')),
            'dimensions' => ['query', 'page'],
            'rowLimit' => 5000,
            'dataState' => 'all',
        ];

        $result = self::api('/sites/' . urlencode($site) . '/searchAnalytics/query', $query_body);

        if (!empty($result['error'])) wp_send_json_error($result['message']);

        $rows = $result['rows'] ?? [];
        if (empty($rows)) {
            wp_send_json_success(['message' => '⚠️ هیچ داده‌ای از GSC دریافت نشد. ممکنه سایت انتخابی داده‌ای نداشته باشه.', 'inserted' => 0, 'total_rows' => 0]);
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'viraseo_gsc_keywords';
        $settings = Dashboard::get();
        $inserted = 0;
        $today = date('Y-m-d');

        foreach ($rows as $row) {
            $kw = $row['keys'][0] ?? '';
            $page = $row['keys'][1] ?? '';
            if (!$kw || !$page) continue;

            $pos = $row['position'] ?? 0;
            $kh = md5(mb_strtolower($kw)); 
            $ph = md5($page);
            $striking = ($pos >= (int)$settings['striking_min'] && $pos <= (int)$settings['striking_max']) ? 1 : 0;

            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE keyword_hash=%s AND page_url_hash=%s AND date_recorded=%s", $kh, $ph, $today));

            $data = [
                'keyword'=>$kw, 'keyword_hash'=>$kh, 
                'page_url'=>$page, 'page_url_hash'=>$ph,
                'post_id'=>url_to_postid($page)?:null,
                'clicks'=>(int)($row['clicks']??0), 
                'impressions'=>(int)($row['impressions']??0),
                'ctr'=>(float)($row['ctr']??0), 
                'position'=>(float)$pos,
                'date_recorded'=>$today, 
                'is_striking'=>(int)$striking,
            ];

            if ($exists) {
                $wpdb->update($table, $data, ['id'=>(int)$exists]);
            } else {
                $result = $wpdb->insert($table, $data, ['%s','%s','%s','%s','%d','%d','%d','%f','%f','%s','%d']);
                if ($result) {
                    $inserted++;
                } else {
                    // Log first error for debugging
                    if ($inserted === 0 && !isset($first_error)) {
                        $first_error = $wpdb->last_error;
                    }
                }
            }
        }

        update_option('viraseo_last_gsc_sync', current_time('mysql'));
        update_option('viraseo_gsc_site', $site);

        // Store a per-page snapshot for winners/losers trend comparison (keep last 6 snapshots)
        $agg = [];
        foreach ($rows as $row) {
            $page = $row['keys'][1] ?? '';
            if (!$page) continue;
            if (!isset($agg[$page])) $agg[$page] = ['c'=>0,'i'=>0,'p'=>0,'n'=>0];
            $agg[$page]['c'] += (int)($row['clicks'] ?? 0);
            $agg[$page]['i'] += (int)($row['impressions'] ?? 0);
            $agg[$page]['p'] += (float)($row['position'] ?? 0);
            $agg[$page]['n']++;
        }
        // Average position per page; keep top 1500 by impressions to bound option size
        foreach ($agg as $u => $v) { $agg[$u]['p'] = $v['n'] ? round($v['p'] / $v['n'], 1) : 0; unset($agg[$u]['n']); }
        uasort($agg, fn($a, $b) => $b['i'] <=> $a['i']);
        $agg = array_slice($agg, 0, 1500, true);
        $snaps = get_option('viraseo_gsc_snapshots', []);
        if (!is_array($snaps)) $snaps = [];
        $snaps[] = ['date' => current_time('Y-m-d H:i'), 'days' => $days, 'pages' => $agg];
        if (count($snaps) > 6) $snaps = array_slice($snaps, -6);
        update_option('viraseo_gsc_snapshots', $snaps, false);

        // Fetch a time-series (by date) for the "نمای زمانی" view — lightweight, one extra call.
        $daily = self::api('/sites/' . urlencode($site) . '/searchAnalytics/query', [
            'startDate' => date('Y-m-d', strtotime("-{$days} days")),
            'endDate' => date('Y-m-d', strtotime('-2 days')),
            'dimensions' => ['date'],
            'rowLimit' => 100,
            'dataState' => 'all',
        ]);
        if (empty($daily['error']) && !empty($daily['rows'])) {
            $series = array_map(fn($r) => [
                'date' => $r['keys'][0] ?? '',
                'clicks' => (int)($r['clicks'] ?? 0),
                'impressions' => (int)($r['impressions'] ?? 0),
                'ctr' => (float)($r['ctr'] ?? 0),
                'position' => (float)($r['position'] ?? 0),
            ], $daily['rows']);
            update_option('viraseo_gsc_daily', $series);
        }
        update_option('viraseo_gsc_range_days', $days);
        
        $response = [
            'message' => sprintf('✅ %d کلمه کلیدی ثبت شد (از %d ردیف GSC).', $inserted, count($rows)),
            'inserted' => $inserted,
            'total_rows' => count($rows),
        ];
        
        // Include debug info if nothing was inserted
        if ($inserted === 0 && count($rows) > 0) {
            $response['debug'] = [
                'last_error' => $first_error ?? $wpdb->last_error,
                'table_exists' => (bool)$wpdb->get_var("SHOW TABLES LIKE '{$table}'"),
                'sample_keyword' => $rows[0]['keys'][0] ?? 'N/A',
                'sample_page' => $rows[0]['keys'][1] ?? 'N/A',
                'table_name' => $table,
            ];
            $response['message'] = '⚠️ ۰ کلمه ثبت شد از ' . count($rows) . ' ردیف. خطا: ' . ($first_error ?? $wpdb->last_error ?: 'نامشخص — ممکنه جدول ساختار متفاوت داشته باشه. افزونه رو غیرفعال و فعال کنید.');
        }
        
        wp_send_json_success($response);
    }
}
