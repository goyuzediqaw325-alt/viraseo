<?php
namespace ViraSEO\Api;
defined('ABSPATH') || exit;

use ViraSEO\Admin\Dashboard;

/**
 * Google OAuth2 — One-Click Connection to Search Console
 * 
 * کاربر فقط دکمه "اتصال به گوگل" می‌زنه:
 * 1. ریدایرکت به صفحه Authorization گوگل
 * 2. گوگل اجازه می‌خواد (scope: webmasters.readonly)
 * 3. برمی‌گرده با code → exchange for tokens → ذخیره
 * 4. از این به بعد API call مستقیم
 *
 * ⚠️ Client ID/Secret: 
 * - اگر کاربر خودش در تنظیمات وارد کرده → استفاده می‌شه
 * - اگر خالی باشه → از مقادیر پیش‌فرض (hardcoded) استفاده می‌شه
 *   (شما باید اینها رو در Google Cloud Console بسازید)
 */
class GoogleOAuth {

    private const TOKEN_OPT = 'viraseo_gsc_token';
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const GSC_API = 'https://www.googleapis.com/webmasters/v3';
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    /**
     * Default Client ID/Secret (owner's Google Cloud project)
     * کاربر نهایی نیازی به وارد کردن اینها نداره — فقط "اتصال" می‌زنه
     * 
     * 🔑 شما باید اینها رو از Google Cloud Console خودتون بسازید:
     *    → APIs & Services → Credentials → OAuth 2.0 Client ID (Web Application)
     *    → Authorized redirect URI: هر دامنه‌ای + /wp-admin/admin-ajax.php?action=viraseo_gsc_callback
     *    
     * سپس مقادیر رو اینجا جایگزین کنید:
     */
    private const DEFAULT_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com';
    private const DEFAULT_CLIENT_SECRET = 'YOUR_GOOGLE_CLIENT_SECRET';

    public function __construct() {
        // OAuth Flow
        add_action('wp_ajax_viraseo_gsc_connect', [$this, 'ajax_redirect_to_google']);
        add_action('wp_ajax_viraseo_gsc_callback', [$this, 'handle_callback']);
        add_action('wp_ajax_viraseo_gsc_disconnect', [$this, 'ajax_disconnect']);
        add_action('wp_ajax_viraseo_gsc_status', [$this, 'ajax_status']);

        // Data fetching
        add_action('wp_ajax_viraseo_gsc_fetch', [$this, 'ajax_fetch_data']);
        add_action('wp_ajax_viraseo_gsc_sites', [$this, 'ajax_get_sites']);
    }

    /**
     * Get Client ID — from settings or default
     */
    private static function client_id(): string {
        $custom = Dashboard::get('gsc_client_id');
        return !empty($custom) ? $custom : self::DEFAULT_CLIENT_ID;
    }

    /**
     * Get Client Secret — from settings or default
     */
    private static function client_secret(): string {
        $custom = Dashboard::get('gsc_client_secret');
        return !empty($custom) ? $custom : self::DEFAULT_CLIENT_SECRET;
    }

    /**
     * Redirect URI (same domain as the WordPress site)
     */
    private static function redirect_uri(): string {
        return admin_url('admin-ajax.php?action=viraseo_gsc_callback');
    }

    /**
     * STEP 1: User clicks "اتصال به گوگل" → redirect to Google consent screen
     * 
     * این AJAX هست ولی به جای JSON، یک redirect URL برمی‌گردونه
     * که JS با window.location.href اون رو باز می‌کنه
     */
    public function ajax_redirect_to_google(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز.');
        }

        $client_id = self::client_id();
        if ($client_id === 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com') {
            wp_send_json_error('⚠️ Client ID تنظیم نشده. لطفاً در تنظیمات افزونه یا مستقیماً در کد، Client ID گوگل خود را وارد کنید.');
        }

        // Generate state nonce for security
        $state = wp_create_nonce('viraseo_gsc_oauth');

        // Build Google OAuth URL
        $auth_url = self::AUTH_URL . '?' . http_build_query([
            'client_id'     => $client_id,
            'redirect_uri'  => self::redirect_uri(),
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',  // Get refresh_token
            'prompt'        => 'consent',   // Always show consent (ensures refresh_token)
            'state'         => $state,
        ]);

        // Return URL for JS to redirect
        wp_send_json_success(['redirect_url' => $auth_url]);
    }

    /**
     * STEP 2: Google redirects back here with ?code=xxx&state=xxx
     * This is NOT an AJAX call — it's a direct browser redirect from Google
     */
    public function handle_callback(): void {
        // Must be admin
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', 403);
        }

        $code = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');
        $error = sanitize_text_field($_GET['error'] ?? '');

        // Handle errors from Google
        if ($error) {
            wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_error=' . urlencode($error)));
            exit;
        }

        // Verify state nonce
        if (!$code || !wp_verify_nonce($state, 'viraseo_gsc_oauth')) {
            wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_error=invalid_state'));
            exit;
        }

        // Exchange code for tokens
        $response = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 30,
            'body' => [
                'code'          => $code,
                'client_id'     => self::client_id(),
                'client_secret' => self::client_secret(),
                'redirect_uri'  => self::redirect_uri(),
                'grant_type'    => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_error=' . urlencode($response->get_error_message())));
            exit;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            $err = $body['error_description'] ?? $body['error'] ?? 'unknown_error';
            wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_error=' . urlencode($err)));
            exit;
        }

        // Save tokens
        update_option(self::TOKEN_OPT, [
            'access_token'  => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? '',
            'expires_at'    => time() + ($body['expires_in'] ?? 3600),
            'connected_at'  => current_time('mysql'),
            'email'         => '', // Will be fetched on first use
        ]);

        // Success! Redirect back to GSC page
        wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_connected=1'));
        exit;
    }

    /**
     * Disconnect — delete stored tokens
     */
    public function ajax_disconnect(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        delete_option(self::TOKEN_OPT);
        wp_send_json_success(['message' => 'اتصال به سرچ کنسول قطع شد.']);
    }

    /**
     * Get connection status
     */
    public function ajax_status(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        $token = get_option(self::TOKEN_OPT);

        if (!$token || empty($token['access_token'])) {
            wp_send_json_success(['connected' => false]);
            return;
        }

        wp_send_json_success([
            'connected'    => true,
            'connected_at' => $token['connected_at'] ?? '',
            'expires_in'   => max(0, ($token['expires_at'] ?? 0) - time()),
        ]);
    }

    /**
     * Get a valid access token (auto-refresh if expired)
     */
    public static function get_access_token(): ?string {
        $token = get_option(self::TOKEN_OPT);
        if (!$token || empty($token['access_token'])) return null;

        // Still valid? (with 60s buffer)
        if (($token['expires_at'] ?? 0) > time() + 60) {
            return $token['access_token'];
        }

        // Need refresh
        if (empty($token['refresh_token'])) return null;

        $response = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 15,
            'body' => [
                'refresh_token' => $token['refresh_token'],
                'client_id'     => self::client_id(),
                'client_secret' => self::client_secret(),
                'grant_type'    => 'refresh_token',
            ],
        ]);

        if (is_wp_error($response)) return null;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) return null;

        // Update stored token
        $token['access_token'] = $body['access_token'];
        $token['expires_at'] = time() + ($body['expires_in'] ?? 3600);
        update_option(self::TOKEN_OPT, $token);

        return $token['access_token'];
    }

    /**
     * Make an authenticated request to GSC API
     */
    public static function api(string $endpoint, array $body = [], string $method = 'POST'): array {
        $access_token = self::get_access_token();
        if (!$access_token) {
            return ['error' => true, 'message' => 'به سرچ کنسول متصل نیستید. ابتدا اتصال برقرار کنید.'];
        }

        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
        ];

        if ($method === 'POST' && $body) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request(self::GSC_API . $endpoint, $args);

        if (is_wp_error($response)) {
            return ['error' => true, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 401) {
            // Token might be revoked
            delete_option(self::TOKEN_OPT);
            return ['error' => true, 'message' => 'توکن منقضی شده. لطفاً مجدداً اتصال برقرار کنید.'];
        }

        if ($code >= 400) {
            return ['error' => true, 'message' => $data['error']['message'] ?? "HTTP {$code}"];
        }

        return $data ?: [];
    }

    /**
     * AJAX: Get list of verified sites in GSC
     */
    public function ajax_get_sites(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        $result = self::api('/sites', [], 'GET');
        if (!empty($result['error'])) {
            wp_send_json_error($result['message']);
        }
        $sites = array_map(fn($s) => $s['siteUrl'] ?? '', $result['siteEntry'] ?? []);
        wp_send_json_success(['sites' => array_filter($sites)]);
    }

    /**
     * AJAX: Fetch keywords from GSC and store in DB
     */
    public function ajax_fetch_data(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $site_url = sanitize_text_field($_POST['site_url'] ?? get_site_url());
        $days = min(90, max(7, absint($_POST['days'] ?? 28)));

        // Call GSC Search Analytics API
        $result = self::api(
            '/sites/' . urlencode($site_url) . '/searchAnalytics/query',
            [
                'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
                'endDate'    => date('Y-m-d', strtotime('-3 days')),
                'dimensions' => ['query', 'page', 'date'],
                'rowLimit'   => 5000,
                'dimensionFilterGroups' => [[
                    'filters' => [['dimension' => 'country', 'expression' => 'irn']]
                ]],
            ]
        );

        if (!empty($result['error'])) {
            wp_send_json_error($result['message']);
        }

        // Store results in DB
        global $wpdb;
        $table = $wpdb->prefix . 'viraseo_gsc_keywords';
        $settings = Dashboard::get();
        $inserted = 0;

        foreach (($result['rows'] ?? []) as $row) {
            if (($row['impressions'] ?? 0) < (int)$settings['min_impressions']) continue;

            $kw = $row['keys'][0] ?? '';
            $page = $row['keys'][1] ?? '';
            $date = $row['keys'][2] ?? '';
            $pos = $row['position'] ?? 0;

            $kh = md5(mb_strtolower($kw));
            $ph = md5($page);
            $striking = ($pos >= (int)$settings['striking_min'] && $pos <= (int)$settings['striking_max']) ? 1 : 0;

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE keyword_hash=%s AND page_url_hash=%s AND date_recorded=%s",
                $kh, $ph, $date
            ));

            $data = [
                'keyword' => $kw, 'keyword_hash' => $kh,
                'page_url' => $page, 'page_url_hash' => $ph,
                'post_id' => url_to_postid($page) ?: null,
                'clicks' => $row['clicks'] ?? 0,
                'impressions' => $row['impressions'] ?? 0,
                'ctr' => $row['ctr'] ?? 0,
                'position' => $pos,
                'date_recorded' => $date,
                'is_striking' => $striking,
            ];

            if ($exists) {
                $wpdb->update($table, $data, ['id' => $exists]);
            } else {
                $wpdb->insert($table, $data);
                $inserted++;
            }
        }

        update_option('viraseo_last_gsc_sync', current_time('mysql'));

        wp_send_json_success([
            'message' => sprintf('✅ همگام‌سازی موفق: %d کلمه کلیدی جدید ثبت شد.', $inserted),
            'total_rows' => count($result['rows'] ?? []),
            'inserted' => $inserted,
        ]);
    }
}
