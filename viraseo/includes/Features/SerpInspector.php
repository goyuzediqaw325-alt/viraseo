<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\PersianText;

/**
 * On-demand deep analysis of a single SERP competitor page.
 * Fetches the page server-side ONLY when the user clicks a result (performance-friendly),
 * then extracts real word count, heading structure, images, schema, and advanced SEO signals.
 */
class SerpInspector {
    public function __construct() {
        add_action('wp_ajax_viraseo_serp_inspect', [$this, 'ajax_inspect']);
    }

    public function ajax_inspect(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $url = esc_url_raw($_POST['url'] ?? '');
        if (!$url) wp_send_json_error('آدرس نامعتبر است.');

        $res = $this->analyze($url);
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success($res);
    }

    /** Fetch a remote page and extract detailed SEO metrics.
     *  Primary path: dedicated n8n workflow (offloads the WP server, avoids host WAF limits).
     *  Fallback: direct server-side fetch from WP. */
    public function analyze(string $url): array {
        // 1) Try the dedicated n8n Page Inspector workflow (synchronous response)
        if (\ViraSEO\Admin\Dashboard::get('n8n_url')) {
            $res = \ViraSEO\Api\WebhookHandler::to_n8n('viraseo-page-inspect', ['url' => $url]);
            if (!isset($res['error']) && is_array($res['data'] ?? null)) {
                $d = $res['data'];
                if (empty($d['error']) && isset($d['word_count']) && (int)$d['word_count'] > 0) {
                    return $this->decorate($d);
                }
            }
            // n8n failed or returned nothing useful — fall through to direct fetch
        }
        return $this->analyze_direct($url);
    }

    /** Add Persian-formatted helpers to a metrics array. */
    private function decorate(array $d): array {
        $d['word_count']     = (int)($d['word_count'] ?? 0);
        $d['word_count_fa']  = PersianText::format_number($d['word_count']);
        $d['word_count_score'] = (int)($d['word_count_score'] ?? 0);
        foreach (['h1','h2','h3','images','images_no_alt','internal_links','external_links','paragraphs'] as $k) {
            $d[$k] = (int)($d[$k] ?? 0);
        }
        $d['h1_texts'] = array_slice((array)($d['h1_texts'] ?? []), 0, 3);
        $d['h2_texts'] = array_slice((array)($d['h2_texts'] ?? []), 0, 12);
        $d['schema']   = array_slice((array)($d['schema'] ?? []), 0, 10);
        $d['title']    = (string)($d['title'] ?? '');
        $d['meta_desc']= (string)($d['meta_desc'] ?? '');
        return $d;
    }

    /** Direct server-side fetch + parse (fallback when n8n is unavailable). */
    public function analyze_direct(string $url): array {
        $r = wp_remote_get($url, [
            'timeout'    => 20,
            'redirection'=> 4,
            'sslverify'  => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            'headers'    => ['Accept' => 'text/html,application/xhtml+xml', 'Accept-Language' => 'fa,en;q=0.8'],
        ]);
        if (is_wp_error($r)) return ['error' => 'خطا در دریافت صفحه: ' . $r->get_error_message()];
        $code = wp_remote_retrieve_response_code($r);
        if ($code < 200 || $code >= 400) return ['error' => "صفحه پاسخ HTTP {$code} داد."];

        $html = wp_remote_retrieve_body($r);
        if (!$html) return ['error' => 'محتوای صفحه خالی بود.'];

        // Strip non-content tags globally first, then isolate <body> (greedy to capture full body)
        $noScripts = preg_replace('/<(script|style|noscript|svg|template|iframe)[^>]*>.*?<\/\1>/si', ' ', $html);
        $body = $noScripts;
        if (preg_match('/<body[^>]*>(.*)<\/body>/si', $noScripts, $bm)) $body = $bm[1];
        $clean = preg_replace('/<(nav|footer|header)[^>]*>.*?<\/\1>/si', ' ', $body);
        $text  = wp_strip_all_tags($clean);
        $text  = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        $word_count = PersianText::word_count($text);
        // Detect JS-rendered pages (large HTML but almost no readable text)
        $js_note = ($word_count < 30 && strlen($html) > 15000)
            ? 'به نظر می‌رسد این صفحه محتوا را با جاوااسکریپت بارگذاری می‌کند؛ تحلیل سمت‌سرور ممکن نیست.' : '';
        $headings   = $this->headings($html);
        $images     = preg_match_all('/<img\b[^>]*>/i', $html, $im) ?: 0;
        $img_no_alt = 0;
        if ($images) foreach ($im[0] as $tag) if (!preg_match('/\balt\s*=\s*["\'][^"\']+["\']/i', $tag)) $img_no_alt++;

        $host = wp_parse_url($url, PHP_URL_HOST);
        $links = $this->count_links($html, $host);

        return [
            'url'         => $url,
            'word_count'  => $word_count,
            'word_count_fa' => PersianText::format_number($word_count),
            'h1'          => $headings['h1'], 'h2' => $headings['h2'], 'h3' => $headings['h3'],
            'h1_texts'    => array_slice($headings['h1_texts'], 0, 3),
            'h2_texts'    => array_slice($headings['h2_texts'], 0, 12),
            'images'      => (int) $images,
            'images_no_alt' => $img_no_alt,
            'internal_links' => $links['internal'],
            'external_links' => $links['external'],
            'title'       => $this->meta_title($html),
            'meta_desc'   => $this->meta_desc($html),
            'schema'      => $this->schema_types($html),
            'word_count_score' => $this->score($word_count, $headings, $images),
            'paragraphs'  => preg_match_all('/<p\b[^>]*>/i', $html) ?: 0,
            'note'        => $js_note,
        ];
    }

    private function headings(string $html): array {
        $out = ['h1'=>0,'h2'=>0,'h3'=>0,'h1_texts'=>[],'h2_texts'=>[]];
        foreach (['h1','h2','h3'] as $tag) {
            if (preg_match_all('/<'.$tag.'\b[^>]*>(.*?)<\/'.$tag.'>/si', $html, $m)) {
                $out[$tag] = count($m[0]);
                if ($tag !== 'h3') foreach ($m[1] as $t) {
                    $clean = trim(wp_strip_all_tags($t));
                    if ($clean !== '') $out[$tag.'_texts'][] = mb_substr($clean, 0, 120);
                }
            }
        }
        return $out;
    }

    private function count_links(string $html, ?string $host): array {
        $internal = 0; $external = 0;
        if (preg_match_all('/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            foreach ($m[1] as $href) {
                if (strpos($href, '#') === 0 || stripos($href, 'javascript:') === 0) continue;
                $h = wp_parse_url($href, PHP_URL_HOST);
                if (!$h || ($host && $h === $host)) $internal++; else $external++;
            }
        }
        return ['internal'=>$internal, 'external'=>$external];
    }

    private function meta_title(string $html): string {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) return trim(wp_strip_all_tags($m[1]));
        return '';
    }

    private function meta_desc(string $html): string {
        if (preg_match('/<meta[^>]+name\s*=\s*["\']description["\'][^>]*content\s*=\s*["\']([^"\']*)["\']/i', $html, $m)) return trim($m[1]);
        if (preg_match('/<meta[^>]+content\s*=\s*["\']([^"\']*)["\'][^>]*name\s*=\s*["\']description["\']/i', $html, $m)) return trim($m[1]);
        return '';
    }

    private function schema_types(string $html): array {
        $types = [];
        if (preg_match_all('/"@type"\s*:\s*"([^"]+)"/i', $html, $m)) $types = array_values(array_unique($m[1]));
        return array_slice($types, 0, 10);
    }

    /** Simple content-quality score 0-100 based on length + structure. */
    private function score(int $words, array $h, int $images): int {
        $s = 0;
        $s += min(50, (int) round($words / 30));      // up to 50 for ~1500 words
        $s += $h['h1'] >= 1 ? 10 : 0;
        $s += min(20, $h['h2'] * 3);
        $s += min(10, $h['h3'] * 2);
        $s += min(10, $images * 2);
        return min(100, $s);
    }
}
