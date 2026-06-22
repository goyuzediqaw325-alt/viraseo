<?php
namespace ViraSEO\Api;
defined('ABSPATH') || exit;

use ViraSEO\Admin\Dashboard;

/**
 * Direct Google OAuth2 for Search Console — NO n8n needed
 * Handles: authorization URL, token exchange, refresh, and GSC API calls
 */
class GoogleOAuth {
    private const TOKEN_OPT = 'viraseo_gsc_token';
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const GSC_API = 'https://www.googleapis.com/webmasters/v3';
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public function __construct() {
        add_action('wp_ajax_viraseo_gsc_auth_url', [$this, 'ajax_auth_url']);
        add_action('wp_ajax_viraseo_gsc_callback', [$this, 'ajax_callback']);
        add_action('wp_ajax_viraseo_gsc_disconnect', [$this, 'ajax_disconnect']);
        add_action('wp_ajax_viraseo_gsc_status', [$this, 'ajax_status']);
        add_action('wp_ajax_viraseo_gsc_fetch', [$this, 'ajax_fetch_data']);
        add_action('wp_ajax_viraseo_gsc_sites', [$this, 'ajax_get_sites']);
    }

    /** Get redirect URI for OAuth callback */
    private static function redirect_uri(): string {
        return admin_url('admin-ajax.php?action=viraseo_gsc_callback');
    }

    /** Generate Google OAuth authorization URL */
    public function ajax_auth_url(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        $client_id = Dashboard::get('gsc_client_id');
        if (!$client_id) wp_send_json_error('Client ID تنظیم نشده. به تنظیمات بروید.');

        $params = http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => self::redirect_uri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => wp_create_nonce('viraseo_gsc_oauth'),
        ]);
        wp_send_json_success(['url' => self::AUTH_URL . '?' . $params]);
    }

    /** Handle OAuth callback — exchange code for tokens */
    public function ajax_callback(): void {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $code = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');

        if (!$code || !wp_verify_nonce($state, 'viraseo_gsc_oauth')) {
            wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_error=invalid_state'));
            exit;
        }

        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'code' => $code,
                'client_id' => Dashboard::get('gsc_client_id'),
                'client_secret' => Dashboard::get('gsc_client_secret'),
                'redirect_uri' => self::redirect_uri(),
                'grant_type' => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_error=' . urlencode($response->get_error_message())));
            exit;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            $err = $body['error_description'] ?? $body['error'] ?? 'unknown';
            wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_error=' . urlencode($err)));
            exit;
        }

        // Store tokens
        update_option(self::TOKEN_OPT, [
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? '',
            'expires_at' => time() + ($body['expires_in'] ?? 3600),
            'connected_at' => current_time('mysql'),
        ]);

        wp_redirect(admin_url('admin.php?page=viraseo-gsc&gsc_connected=1'));
        exit;
    }

    /** Disconnect GSC */
    public function ajax_disconnect(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        delete_option(self::TOKEN_OPT);
        wp_send_json_success('اتصال قطع شد.');
    }

    /** Get connection status */
    public function ajax_status(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        $token = get_option(self::TOKEN_OPT);
        if (!$token || empty($token['access_token'])) {
            wp_send_json_success(['connected' => false]);
            return;
        }
        wp_send_json_success([
            'connected' => true,
            'connected_at' => $token['connected_at'] ?? '',
            'expires_in' => max(0, ($token['expires_at'] ?? 0) - time()),
        ]);
    }

    /** Refresh access token if expired */
    private static function get_valid_token(): ?string {
        $token = get_option(self::TOKEN_OPT);
        if (!$token || empty($token['access_token'])) return null;

        // Still valid?
        if (($token['expires_at'] ?? 0) > time() + 60) {
            return $token['access_token'];
        }

        // Refresh
        if (empty($token['refresh_token'])) return null;

        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'refresh_token' => $token['refresh_token'],
                'client_id' => Dashboard::get('gsc_client_id'),
                'client_secret' => Dashboard::get('gsc_client_secret'),
                'grant_type' => 'refresh_token',
            ],
        ]);

        if (is_wp_error($response)) return null;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) return null;

        $token['access_token'] = $body['access_token'];
        $token['expires_at'] = time() + ($body['expires_in'] ?? 3600);
        update_option(self::TOKEN_OPT, $token);

        return $token['access_token'];
    }

    /** Make authenticated GSC API request */
    public static function api_request(string $endpoint, array $body = [], string $method = 'POST'): array {
        $access_token = self::get_valid_token();
        if (!$access_token) return ['error' => 'not_connected', 'message' => 'GSC متصل نیست.'];

        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
        ];
        if ($method === 'POST' && $body) $args['body'] = wp_json_encode($body);

        $url = self::GSC_API . $endpoint;
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) return ['error' => 'request_failed', 'message' => $response->get_error_message()];

        return json_decode(wp_remote_retrieve_body($response), true) ?: [];
    }

    /** Get list of verified sites in GSC */
    public function ajax_get_sites(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        $result = self::api_request('/sites', [], 'GET');
        if (isset($result['error'])) wp_send_json_error($result['message']);
        $sites = array_map(fn($s) => $s['siteUrl'] ?? '', $result['siteEntry'] ?? []);
        wp_send_json_success(['sites' => array_filter($sites)]);
    }

    /** AJAX: Fetch GSC keyword data directly */
    public function ajax_fetch_data(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $site_url = sanitize_text_field($_POST['site_url'] ?? get_site_url());
        $days = absint($_POST['days'] ?? 28);

        $result = self::api_request(
            '/sites/' . urlencode($site_url) . '/searchAnalytics/query',
            [
                'startDate' => date('Y-m-d', strtotime("-{$days} days")),
                'endDate' => date('Y-m-d', strtotime('-3 days')),
                'dimensions' => ['query', 'page', 'date'],
                'rowLimit' => 5000,
                'dimensionFilterGroups' => [[
                    'filters' => [['dimension' => 'country', 'expression' => 'irn']]
                ]],
            ]
        );

        if (isset($result['error'])) wp_send_json_error($result['message'] ?? 'خطای API');

        // Store in DB
        global $wpdb;
        $table = $wpdb->prefix . 'viraseo_gsc_keywords';
        $settings = Dashboard::get();
        $inserted = 0;

        foreach (($result['rows'] ?? []) as $row) {
            if (($row['impressions'] ?? 0) < $settings['min_impressions']) continue;
            $kw = $row['keys'][0] ?? '';
            $page = $row['keys'][1] ?? '';
            $date = $row['keys'][2] ?? '';
            $kh = md5(mb_strtolower($kw));
            $ph = md5($page);
            $pos = $row['position'] ?? 0;
            $striking = ($pos >= $settings['striking_min'] && $pos <= $settings['striking_max']) ? 1 : 0;

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE keyword_hash=%s AND page_url_hash=%s AND date_recorded=%s",
                $kh, $ph, $date
            ));

            $data = [
                'keyword'=>$kw,'keyword_hash'=>$kh,'page_url'=>$page,'page_url_hash'=>$ph,
                'post_id'=>url_to_postid($page)?:null,
                'clicks'=>$row['clicks']??0,'impressions'=>$row['impressions']??0,
                'ctr'=>$row['ctr']??0,'position'=>$pos,
                'date_recorded'=>$date,'is_striking'=>$striking,
            ];

            if ($exists) $wpdb->update($table, $data, ['id'=>$exists]);
            else { $wpdb->insert($table, $data); $inserted++; }
        }

        update_option('viraseo_last_gsc_sync', current_time('mysql'));
        wp_send_json_success([
            'message' => sprintf('همگام‌سازی انجام شد: %d کلمه جدید ثبت شد.', $inserted),
            'total_rows' => count($result['rows'] ?? []),
            'inserted' => $inserted,
        ]);
    }
}
