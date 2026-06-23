<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Api\GoogleOAuth;
use ViraSEO\Utils\JalaliDate;

/**
 * Index Status [🟢 GSC URL Inspection API]
 * Checks whether pages are correctly indexed by Google and surfaces indexing problems.
 */
class IndexStatus {
    public function __construct() {
        add_action('wp_ajax_viraseo_index_inspect', [$this, 'ajax_inspect']);
        add_action('wp_ajax_viraseo_index_batch', [$this, 'ajax_batch']);
        add_action('wp_ajax_viraseo_index_request', [$this, 'ajax_request']);
    }

    /** Ask Google to (re)crawl a URL via the Indexing API. */
    public function ajax_request(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $url = esc_url_raw($_POST['url'] ?? '');
        if (!$url) wp_send_json_error('آدرس نامعتبر.');
        $res = GoogleOAuth::request_indexing($url);
        if (!empty($res['error'])) wp_send_json_error($res['message']);
        wp_send_json_success(['message' => $res['message']]);
    }

    private function site(): string {
        $s = sanitize_text_field($_POST['site'] ?? '');
        return $s ?: (string) get_option('viraseo_gsc_site', get_site_url());
    }

    /** Map a raw URL Inspection result into a Persian-friendly summary. */
    private function parse(array $res, string $url): array {
        $r = $res['inspectionResult']['indexStatusResult'] ?? [];
        $verdict = $r['verdict'] ?? 'UNKNOWN';
        $coverage = $r['coverageState'] ?? '—';
        $indexed = ($verdict === 'PASS');
        $problems = [];
        if (($r['robotsTxtState'] ?? '') === 'DISALLOWED') $problems[] = 'توسط robots.txt مسدود شده';
        if (!empty($r['pageFetchState']) && $r['pageFetchState'] !== 'SUCCESSFUL') $problems[] = 'مشکل در دریافت صفحه: ' . $r['pageFetchState'];
        if (($r['indexingState'] ?? '') === 'BLOCKED_BY_META_TAG') $problems[] = 'تگ noindex فعال است';
        $gc = $r['googleCanonical'] ?? ''; $uc = $r['userCanonical'] ?? '';
        if ($gc && $uc && $gc !== $uc) $problems[] = 'کانونیکال گوگل با کانونیکال شما فرق دارد';

        return [
            'url' => $url,
            'indexed' => $indexed,
            'verdict' => $verdict,
            'coverage' => $coverage,
            'last_crawl' => !empty($r['lastCrawlTime']) ? JalaliDate::format($r['lastCrawlTime'], 'long') : 'هرگز',
            'problems' => $problems,
        ];
    }

    public function ajax_inspect(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $url = esc_url_raw($_POST['url'] ?? '');
        if (!$url) wp_send_json_error('آدرس نامعتبر.');
        $res = GoogleOAuth::inspect($this->site(), $url);
        if (!empty($res['error'])) wp_send_json_error($res['message']);
        wp_send_json_success($this->parse($res, $url));
    }

    /** Inspect a batch of recent published pages (capped to respect API quota). */
    public function ajax_batch(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $site = $this->site();
        $limit = max(1, min(25, absint($_POST['limit'] ?? 15)));
        $ids = get_posts(['post_type'=>TargetKeywords::public_types(), 'post_status'=>'publish', 'numberposts'=>$limit, 'orderby'=>'modified', 'order'=>'DESC', 'fields'=>'ids']);
        $rows = []; $indexed = 0; $issues = 0;
        foreach ($ids as $pid) {
            if (TargetKeywords::is_excluded((int)$pid)) continue;
            $url = get_permalink($pid);
            $res = GoogleOAuth::inspect($site, $url);
            if (!empty($res['error'])) {
                // Stop early on auth/quota errors and report
                wp_send_json_error($res['message'] . ' (پس از ' . count($rows) . ' بررسی)');
            }
            $p = $this->parse($res, $url);
            $p['title'] = get_the_title($pid) ?: $url;
            $p['edit'] = get_edit_post_link($pid, 'raw');
            if ($p['indexed']) $indexed++;
            if ($p['problems']) $issues++;
            $rows[] = $p;
            usleep(200000); // be gentle on the API
        }
        wp_send_json_success(['rows'=>$rows, 'indexed'=>$indexed, 'issues'=>$issues, 'total'=>count($rows)]);
    }
}
