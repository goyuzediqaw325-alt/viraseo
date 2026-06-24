<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\PersianText;
use ViraSEO\Admin\Dashboard;

/**
 * On-demand deep analysis of a single SERP competitor page.
 * Fetches the page server-side ONLY when the user clicks a result (performance-friendly),
 * then extracts real word count, heading structure, images, schema, and advanced SEO signals.
 */
class SerpInspector {

    /** Multiple User-Agents for rotation to avoid blocks. */
    private array $user_agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Edg/119.0.0.0',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    ];

    public function __construct() {
        add_action('wp_ajax_viraseo_serp_inspect', [$this, 'ajax_inspect']);
        add_action('wp_ajax_viraseo_serp_inspect_full', [$this, 'ajax_inspect_full']);
        add_action('wp_ajax_viraseo_serp_competitor_analysis', [$this, 'ajax_competitor_analysis']);
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

    /** Full single-competitor analysis with keyword-specific metrics. */
    public function ajax_inspect_full(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $url = esc_url_raw($_POST['url'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        if (!$url) wp_send_json_error('آدرس نامعتبر است.');
        if (!$keyword) wp_send_json_error('کلمه کلیدی وارد کنید.');

        $keyword = PersianText::normalize($keyword);
        $res = $this->analyze($url, $keyword);
        if (isset($res['error'])) wp_send_json_error($res['error']);

        // Add keyword-specific analysis
        $res['keyword_analysis'] = $this->keyword_analysis($res, $keyword, $url);
        wp_send_json_success($res);
    }

    /** Batch deep analysis of all competitors for an analysis_id. */
    public function ajax_competitor_analysis(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        global $wpdb;
        $analysis_id = absint($_POST['analysis_id'] ?? 0);
        if (!$analysis_id) wp_send_json_error('شناسه تحلیل نامعتبر.');

        $ct = $wpdb->prefix . 'viraseo_serp_competitors';
        $at = $wpdb->prefix . 'viraseo_serp_analysis';
        $analysis = $wpdb->get_row($wpdb->prepare("SELECT keyword FROM {$at} WHERE id=%d", $analysis_id));
        if (!$analysis) wp_send_json_error('تحلیل یافت نشد.');

        $keyword = PersianText::normalize($analysis->keyword);
        $comps = $wpdb->get_results($wpdb->prepare("SELECT id, url FROM {$ct} WHERE analysis_id=%d ORDER BY position", $analysis_id));
        if (!$comps) wp_send_json_error('رقیبی یافت نشد.');

        $results = [];
        $words_all = [];
        $heads_all = [];
        foreach ($comps as $comp) {
            $res = $this->analyze($comp->url, $keyword);
            if (isset($res['error'])) {
                $results[] = ['url' => $comp->url, 'error' => $res['error']];
                continue;
            }
            $wc = (int)($res['word_count'] ?? 0);
            if ($wc > 0) {
                $words_all[] = $wc;
                $heads_all[] = ($res['h1'] ?? 0) + ($res['h2'] ?? 0) + ($res['h3'] ?? 0);
            }
            // Persist enhanced results
            $wpdb->update($ct, [
                'word_count'     => $wc,
                'h1_count'       => (int)($res['h1'] ?? 0),
                'h2_count'       => (int)($res['h2'] ?? 0),
                'h3_count'       => (int)($res['h3'] ?? 0),
                'images_count'   => (int)($res['images'] ?? 0),
                'internal_links' => (int)($res['internal_links'] ?? 0),
                'external_links' => (int)($res['external_links'] ?? 0),
                'response_time'  => (int)($res['response_time'] ?? 0),
                'canonical_url'  => mb_substr((string)($res['canonical_url'] ?? ''), 0, 2048),
                'has_faq'        => (int)($res['has_faq'] ?? 0),
                'has_video'      => (int)($res['has_video'] ?? 0),
                'has_table'      => (int)($res['has_table'] ?? 0),
                'reading_level'  => (int)($res['reading_level'] ?? 0),
                'top_keywords'   => wp_json_encode($res['top_keywords'] ?? []),
                'meta_title'     => mb_substr((string)($res['title'] ?? ''), 0, 500),
                'meta_desc'      => (string)($res['meta_desc'] ?? ''),
            ], ['id' => $comp->id]);

            $results[] = $res;
        }

        // Update averages
        $avg_words = $words_all ? (int)round(array_sum($words_all) / count($words_all)) : 0;
        $max_words = $words_all ? max($words_all) : 0;
        $avg_heads = $heads_all ? (int)round(array_sum($heads_all) / count($heads_all)) : 0;
        $wpdb->update($at, ['avg_word_count' => $avg_words, 'avg_headings' => $avg_heads], ['id' => $analysis_id]);

        $target_words = $max_words ? (int)round($max_words * 1.1) : $avg_words;
        $rec = sprintf(
            'برای پیشی گرفتن از رقبا: محتوایی حدود %s کلمه (رقیب برتر: %s) با حداقل %s هدینگ بنویسید.',
            PersianText::format_number($target_words),
            PersianText::format_number($max_words),
            PersianText::format_number(max(1, $avg_heads))
        );

        wp_send_json_success([
            'results'        => $results,
            'avg_words'      => PersianText::format_number($avg_words),
            'max_words'      => PersianText::format_number($max_words),
            'avg_headings'   => PersianText::format_number($avg_heads),
            'recommendation' => $rec,
        ]);
    }

    /** Fetch a remote page and extract detailed SEO metrics.
     *  Primary path: dedicated n8n workflow (offloads the WP server, avoids host WAF limits).
     *  Fallback: direct server-side fetch from WP. */
    public function analyze(string $url, string $keyword = ''): array {
        // Respect the inspect_mode setting
        $mode = Dashboard::get('inspect_mode') ?: 'direct';

        // If mode=n8n and n8n is configured, try n8n first
        if ($mode === 'n8n' && Dashboard::get('n8n_url')) {
            $res = \ViraSEO\Api\WebhookHandler::to_n8n('viraseo-page-inspect', [
                'url' => $url,
                'keyword' => $keyword,
                'browserless_token' => Dashboard::get('browserless_token'),
            ]);
            if (!isset($res['error']) && is_array($res['data'] ?? null)) {
                $d = $res['data'];
                if (empty($d['error']) && isset($d['word_count']) && (int)$d['word_count'] > 0) {
                    return $this->decorate($d);
                }
            }
            // n8n failed — fall through to direct fetch
        }
        return $this->analyze_direct($url, $keyword);
    }

    /** Add Persian-formatted helpers + SEO strengths/weaknesses to a metrics array. */
    private function decorate(array $d): array {
        $d['word_count']     = (int)($d['word_count'] ?? 0);
        $d['word_count_fa']  = PersianText::format_number($d['word_count']);
        $d['word_count_score'] = (int)($d['word_count_score'] ?? 0);
        foreach (['h1','h2','h3','images','images_no_alt','internal_links','external_links','paragraphs','tables','videos'] as $k) {
            $d[$k] = (int)($d[$k] ?? 0);
        }
        $d['h1_texts'] = array_slice((array)($d['h1_texts'] ?? []), 0, 3);
        $d['h2_texts'] = array_slice((array)($d['h2_texts'] ?? []), 0, 12);
        $d['schema']   = array_slice((array)($d['schema'] ?? []), 0, 10);
        $d['title']    = (string)($d['title'] ?? '');
        $d['meta_desc']= (string)($d['meta_desc'] ?? '');
        $d['strengths'] = (array)($d['strengths'] ?? []);
        $d['weaknesses'] = (array)($d['weaknesses'] ?? []);
        $d['top_keywords'] = (array)($d['top_keywords'] ?? []);
        $d['keyword_analysis'] = (array)($d['keyword_analysis'] ?? []);
        $d['ngrams'] = (array)($d['ngrams'] ?? []);
        $d['paragraph_keywords'] = (array)($d['paragraph_keywords'] ?? []);
        $d['content_density'] = (array)($d['content_density'] ?? []);
        $d['seo_factors'] = (array)($d['seo_factors'] ?? []);
        return $d;
    }

    /** Direct server-side fetch + parse (fallback when n8n is unavailable). */
    public function analyze_direct(string $url, string $keyword = ''): array {
        $start_time = microtime(true);
        $html = '';
        $code = 0;
        $proxy = Dashboard::get('ai_curl_proxy');

        // Strategy: try proxy FIRST if configured (Iran hosts often blocked by target sites)
        // Then fall back to direct fetch if proxy failed.
        $methods = $proxy ? ['proxy', 'direct'] : ['direct'];
        $shuffled_uas = $this->user_agents;
        shuffle($shuffled_uas);

        foreach ($methods as $method) {
            if ($html && strlen($html) > 500) break; // already got content

            if ($method === 'proxy') {
                // Use raw cURL with proxy for maximum compatibility
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 25,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_PROXY => $proxy,
                    CURLOPT_USERAGENT => $shuffled_uas[0],
                    CURLOPT_HTTPHEADER => [
                        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language: fa,en;q=0.8',
                        'Accept-Encoding: gzip, deflate',
                        'Cache-Control: no-cache',
                    ],
                    CURLOPT_ENCODING => '', // auto decompress gzip/deflate
                ]);
                if (strpos($proxy, '@') !== false) curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
                $html = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($html && $code >= 200 && $code < 400) continue;
                $html = ''; // reset for next method
            } else {
                // Direct fetch with UA rotation
                $attempts = min(count($shuffled_uas), 3);
                for ($i = 0; $i < $attempts; $i++) {
                    $r = wp_remote_get($url, [
                        'timeout'     => 20,
                        'redirection' => 4,
                        'sslverify'   => false,
                        'user-agent'  => $shuffled_uas[$i],
                        'headers'     => [
                            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                            'Accept-Language'  => 'fa,en;q=0.8',
                            'Accept-Encoding'  => 'gzip, deflate',
                            'Cache-Control'    => 'no-cache',
                            'Connection'       => 'keep-alive',
                        ],
                    ]);
                    if (is_wp_error($r)) continue;
                    $code = wp_remote_retrieve_response_code($r);
                    if ($code >= 200 && $code < 400) {
                        $html = wp_remote_retrieve_body($r);
                        if ($html && strlen($html) > 500) break;
                    }
                }
            }
        }

        // If still no content after proxy+direct, try one more time with proxy + different UA
        if ((!$html || strlen($html) < 200) && $proxy) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_PROXY => $proxy,
                CURLOPT_USERAGENT => $shuffled_uas[1] ?? $shuffled_uas[0],
                CURLOPT_ENCODING => '',
            ]);
            if (strpos($proxy, '@') !== false) curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            $html = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }

        $response_time = (int)round((microtime(true) - $start_time) * 1000);

        if (!$html) return ['error' => 'خطا در دریافت صفحه: محتوا خالی بود.'];
        if ($code < 200 || $code >= 400) return ['error' => "صفحه پاسخ HTTP {$code} داد."];

        // Extract enhanced content with JS-rendered page fallback
        $text = $this->extract_content($html);
        $word_count = PersianText::word_count($text);

        // Retry with noscript/JSON-LD fallback if word count is 0
        if ($word_count < 30) {
            $fallback_text = $this->extract_fallback_content($html);
            if ($fallback_text && PersianText::word_count($fallback_text) > $word_count) {
                $text = $fallback_text;
                $word_count = PersianText::word_count($text);
            }
        }

        // JS-rendered page detection
        $js_note = '';
        if ($word_count < 50 && strlen($html) > 10000) {
            $js_note = 'به‌نظر می‌رسد این صفحه محتوا را با JavaScript رندر می‌کند (HTML دریافت‌شده بزرگ ولی محتوای متنی کم). تعداد کلمات و هدینگ‌ها کمتر از واقعیت نشان داده می‌شوند. برای تحلیل دقیق‌تر، سایت‌هایی که محتوا را سمت سرور (SSR) رندر می‌کنند قابل خواندن هستند.';
        } elseif ($word_count < 50 && strlen($html) < 2000) {
            $js_note = 'صفحه پاسخ بسیار کوتاهی برگرداند. ممکن است سایت رقیب IP شما/هاست‌تان را بلاک کرده باشد.';
        }

        // Even for JS-rendered pages, we can still extract metadata from <head>
        $title = $this->meta_title($html);
        $meta_desc_val = $this->meta_desc($html);
        // If title is empty, try og:title
        if (!$title) $title = $this->extract_og($html, 'title');
        if (!$meta_desc_val) $meta_desc_val = $this->extract_og($html, 'description');

        $headings      = $this->headings($html);
        $images        = preg_match_all('/<img\b[^>]*>/i', $html, $im) ?: 0;
        $img_no_alt    = 0;
        if ($images) foreach ($im[0] as $tag) if (!preg_match('/\balt\s*=\s*["\'][^"\']+["\']/i', $tag)) $img_no_alt++;

        $host          = wp_parse_url($url, PHP_URL_HOST);
        $links         = $this->count_links($html, $host);
        $canonical_url = $this->extract_canonical($html);
        $og_title      = $this->extract_og($html, 'title');
        $og_desc       = $this->extract_og($html, 'description');
        $robots_meta   = $this->extract_robots_meta($html);
        $content_type  = $this->detect_content_type($html, $url);
        $reading_level = $this->estimate_reading_level($text, $word_count);
        $tables        = preg_match_all('/<table\b/i', $html) ?: 0;
        $videos        = $this->count_videos($html);
        $has_faq       = $this->detect_faq($html);
        $top_keywords  = $this->extract_top_keywords($text);
        $section_words = $this->word_count_per_section($html);

        // Keyword density (if keyword provided)
        $keyword_density = 0.0;
        if ($keyword && $word_count > 0) {
            $kw_count = mb_substr_count(mb_strtolower($text), mb_strtolower($keyword));
            $keyword_density = round(($kw_count / $word_count) * 100, 2);
        }

        // Enhanced analysis: n-grams, paragraph keywords, density, SEO factors
        $ngrams = $this->extract_ngrams($text);
        $paragraph_keywords = $this->extract_paragraph_keywords($html);
        $content_density = $this->content_density_analysis($html, $text, $word_count);
        $schema_types = $this->schema_types($html);
        $seo_factors = $this->seo_factors_analysis($html, $text, $word_count, $headings, (int)$images, $img_no_alt, $links, $title, $meta_desc_val, $keyword, $keyword_density, $url);

        return [
            'url'              => $url,
            'word_count'       => $word_count,
            'word_count_fa'    => PersianText::format_number($word_count),
            'h1'               => $headings['h1'],
            'h2'               => $headings['h2'],
            'h3'               => $headings['h3'],
            'h1_texts'         => array_slice($headings['h1_texts'], 0, 3),
            'h2_texts'         => array_slice($headings['h2_texts'], 0, 12),
            'images'           => (int)$images,
            'images_no_alt'    => $img_no_alt,
            'internal_links'   => $links['internal'],
            'external_links'   => $links['external'],
            'title'            => $title,
            'meta_desc'        => $meta_desc_val,
            'schema'           => $schema_types,
            'word_count_score' => $this->score($word_count, $headings, $images),
            'paragraphs'       => preg_match_all('/<p\b[^>]*>/i', $html) ?: 0,
            'note'             => $js_note,
            'response_time'    => $response_time,
            'canonical_url'    => $canonical_url,
            'og_title'         => $og_title,
            'og_description'   => $og_desc,
            'robots_meta'      => $robots_meta,
            'content_type'     => $content_type,
            'reading_level'    => $reading_level,
            'keyword_density'  => $keyword_density,
            'top_keywords'     => $top_keywords,
            'tables'           => $tables,
            'videos'           => $videos,
            'has_faq'          => $has_faq ? 1 : 0,
            'has_video'        => $videos > 0 ? 1 : 0,
            'has_table'        => $tables > 0 ? 1 : 0,
            'section_words'    => $section_words,
            'ngrams'           => $ngrams,
            'paragraph_keywords' => $paragraph_keywords,
            'content_density'  => $content_density,
            'seo_factors'      => $seo_factors,
            'strengths'        => $this->build_strengths($word_count, $headings, (int)$images, $tables, $has_faq, $videos, $links, $schema_types, $title, $meta_desc_val, $keyword, $keyword_density),
            'weaknesses'       => $this->build_weaknesses($word_count, $headings, (int)$images, $img_no_alt, $tables, $has_faq, $videos, $links, $schema_types, $title, $meta_desc_val, $keyword, $keyword_density),
        ];
    }

    /** Extract main body content with improved logic. */
    private function extract_content(string $html): string {
        $noScripts = preg_replace('/<(script|style|svg|template|iframe)[^>]*>.*?<\/\1>/si', ' ', $html);
        $body = $noScripts;
        if (preg_match('/<body[^>]*>(.*)<\/body>/si', $noScripts, $bm)) $body = $bm[1];
        // Remove nav/footer/header/aside
        $clean = preg_replace('/<(nav|footer|header|aside)[^>]*>.*?<\/\1>/si', ' ', $body);

        // Try main tag or article first
        $extracted = '';
        if (preg_match('/<main[^>]*>(.*?)<\/main>/si', $clean, $mm)) {
            $extracted = $mm[1];
        } elseif (preg_match('/<article[^>]*>(.*?)<\/article>/si', $clean, $am)) {
            $extracted = $am[1];
        }

        // Check if extraction is adequate (>= 30 words)
        if ($extracted) {
            $test_text = wp_strip_all_tags($extracted);
            $test_text = html_entity_decode($test_text, ENT_QUOTES, 'UTF-8');
            if (PersianText::word_count($test_text) >= 30) {
                return $test_text;
            }
        }

        // Try additional content container selectors
        $selectors = [
            '/<div[^>]*class\s*=\s*["\'][^"\']*\bentry-content\b[^"\']*["\'][^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class\s*=\s*["\'][^"\']*\bpost-content\b[^"\']*["\'][^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class\s*=\s*["\'][^"\']*\bproduct-description\b[^"\']*["\'][^>]*>(.*?)<\/div>/si',
            '/<div[^>]*id\s*=\s*["\']content["\'][^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class\s*=\s*["\'][^"\']*\bwoocommerce-product-content\b[^"\']*["\'][^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class\s*=\s*["\'][^"\']*\belementor-widget-container\b[^"\']*["\'][^>]*>(.*?)<\/div>/si',
            '/<[^>]*role\s*=\s*["\']main["\'][^>]*>(.*?)<\/[a-z]+>/si',
            '/data-content[^>]*>(.*?)<\/div>/si',
        ];

        foreach ($selectors as $pattern) {
            if (preg_match($pattern, $clean, $sm)) {
                $candidate = wp_strip_all_tags($sm[1]);
                $candidate = html_entity_decode($candidate, ENT_QUOTES, 'UTF-8');
                if (PersianText::word_count($candidate) >= 30) {
                    return $candidate;
                }
            }
        }

        // Greedy capture: everything between first H1/H2 and the last content-bearing tag
        if (preg_match('/<h[12]\b[^>]*>(.*)/si', $clean, $hm)) {
            $after_heading = $hm[1];
            // Strip from the last sidebar/widget area onward
            $after_heading = preg_replace('/<(aside|footer|nav)[^>]*>.*$/si', '', $after_heading);
            $greedy_text = wp_strip_all_tags($after_heading);
            $greedy_text = html_entity_decode($greedy_text, ENT_QUOTES, 'UTF-8');
            if (PersianText::word_count($greedy_text) >= 30) {
                return $greedy_text;
            }
        }

        // Final fallback: full body minus nav/header/footer/sidebar (already in $clean)
        $text = wp_strip_all_tags($clean);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return $text;
    }

    /** Fallback content extraction for JS-rendered pages. */
    private function extract_fallback_content(string $html): string {
        $text = '';
        // Check noscript tags
        if (preg_match_all('/<noscript[^>]*>(.*?)<\/noscript>/si', $html, $ns)) {
            $combined = implode(' ', $ns[1]);
            $t = wp_strip_all_tags($combined);
            $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
            if (PersianText::word_count($t) > 30) $text = $t;
        }
        // Check JSON-LD articleBody
        if (!$text && preg_match('/"articleBody"\s*:\s*"([^"]+)"/i', $html, $ab)) {
            $decoded = stripcslashes($ab[1]);
            if (PersianText::word_count($decoded) > 30) $text = $decoded;
        }
        // Check data-content attributes
        if (!$text && preg_match_all('/data-content\s*=\s*["\']([^"\']+)["\']/i', $html, $dc)) {
            $combined = implode(' ', $dc[1]);
            $decoded = html_entity_decode($combined, ENT_QUOTES, 'UTF-8');
            if (PersianText::word_count($decoded) > 30) $text = $decoded;
        }
        return $text;
    }

    /** Keyword-specific analysis. */
    private function keyword_analysis(array $data, string $keyword, string $url): array {
        $kw_lower = mb_strtolower($keyword);
        $title = mb_strtolower((string)($data['title'] ?? ''));
        $meta = mb_strtolower((string)($data['meta_desc'] ?? ''));
        $url_lower = mb_strtolower($url);
        $h1_texts = array_map('mb_strtolower', $data['h1_texts'] ?? []);
        $h2_texts = array_map('mb_strtolower', $data['h2_texts'] ?? []);

        $in_title = mb_strpos($title, $kw_lower) !== false ? 1 : 0;
        $in_url = mb_strpos($url_lower, $kw_lower) !== false ? 1 : 0;
        $in_meta = mb_strpos($meta, $kw_lower) !== false ? 1 : 0;
        $in_h1 = 0;
        foreach ($h1_texts as $h) if (mb_strpos($h, $kw_lower) !== false) { $in_h1 = 1; break; }
        $in_h2 = 0;
        foreach ($h2_texts as $h) if (mb_strpos($h, $kw_lower) !== false) { $in_h2 = 1; break; }

        // Prominence score (0-100)
        $prominence = 0;
        $prominence += $in_title ? 25 : 0;
        $prominence += $in_h1 ? 20 : 0;
        $prominence += $in_h2 ? 10 : 0;
        $prominence += $in_meta ? 15 : 0;
        $prominence += $in_url ? 15 : 0;
        $density = (float)($data['keyword_density'] ?? 0);
        $prominence += min(15, (int)round($density * 5));

        $recommendations = [];
        if (!$in_title) $recommendations[] = 'کلمه کلیدی در عنوان صفحه وجود ندارد.';
        if (!$in_h1) $recommendations[] = 'کلمه کلیدی در H1 استفاده نشده.';
        if (!$in_meta) $recommendations[] = 'کلمه کلیدی در متا دسکریپشن نیست.';
        if ($density < 0.5) $recommendations[] = 'تراکم کلمه کلیدی خیلی کم است (کمتر از ۰.۵٪).';
        if ($density > 3.0) $recommendations[] = 'تراکم کلمه کلیدی خیلی زیاد است (بیش از ۳٪). خطر اسپم!';

        return [
            'in_title'       => $in_title,
            'in_h1'          => $in_h1,
            'in_h2'          => $in_h2,
            'in_meta'        => $in_meta,
            'in_url'         => $in_url,
            'prominence'     => min(100, $prominence),
            'density'        => $density,
            'recommendations'=> $recommendations,
        ];
    }

    /** Extract canonical URL from HTML. */
    private function extract_canonical(string $html): string {
        if (preg_match('/<link[^>]+rel\s*=\s*["\']canonical["\'][^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) return trim($m[1]);
        if (preg_match('/<link[^>]+href\s*=\s*["\']([^"\']+)["\'][^>]*rel\s*=\s*["\']canonical["\']/i', $html, $m)) return trim($m[1]);
        return '';
    }

    /** Extract Open Graph meta values. */
    private function extract_og(string $html, string $property): string {
        if (preg_match('/<meta[^>]+property\s*=\s*["\']og:'.$property.'["\'][^>]*content\s*=\s*["\']([^"\']*)["\']/', $html, $m)) return trim($m[1]);
        if (preg_match('/<meta[^>]+content\s*=\s*["\']([^"\']*)["\'][^>]*property\s*=\s*["\']og:'.$property.'["\']/i', $html, $m)) return trim($m[1]);
        return '';
    }

    /** Extract robots meta directive. */
    private function extract_robots_meta(string $html): string {
        if (preg_match('/<meta[^>]+name\s*=\s*["\']robots["\'][^>]*content\s*=\s*["\']([^"\']*)["\']/', $html, $m)) return trim($m[1]);
        if (preg_match('/<meta[^>]+content\s*=\s*["\']([^"\']*)["\'][^>]*name\s*=\s*["\']robots["\']/i', $html, $m)) return trim($m[1]);
        return 'index, follow';
    }

    /** Detect content type (article, product, landing). */
    private function detect_content_type(string $html, string $url): string {
        $url_lower = strtolower($url);
        $product_signals = ['/product', '/shop', '/store', '/cart', 'woocommerce', 'add-to-cart'];
        foreach ($product_signals as $s) if (strpos($url_lower, $s) !== false) return 'product';
        if (preg_match('/"@type"\s*:\s*"Product"/i', $html)) return 'product';
        if (preg_match('/"@type"\s*:\s*"Article"/i', $html) || preg_match('/"@type"\s*:\s*"BlogPosting"/i', $html)) return 'article';
        if (preg_match('/<article\b/i', $html)) return 'article';
        $article_urls = ['/blog', '/article', '/mag', '/news', '/post'];
        foreach ($article_urls as $s) if (strpos($url_lower, $s) !== false) return 'article';
        return 'landing';
    }

    /** Estimate reading level (1-100, higher = harder). */
    private function estimate_reading_level(string $text, int $word_count): int {
        if ($word_count < 10) return 0;
        // Simple heuristic: average word length * sentence complexity
        $sentences = max(1, preg_match_all('/[.!?\x{061F}\x{06D4}]/u', $text));
        $avg_words_per_sentence = $word_count / $sentences;
        $chars = mb_strlen(preg_replace('/\s+/u', '', $text));
        $avg_word_length = $chars / max(1, $word_count);
        $level = (int)round(min(100, ($avg_words_per_sentence * 2) + ($avg_word_length * 8)));
        return max(1, min(100, $level));
    }

    /** Count video embeds. */
    private function count_videos(string $html): int {
        $count = 0;
        $count += preg_match_all('/<video\b/i', $html) ?: 0;
        $count += preg_match_all('/youtube\.com\/embed/i', $html) ?: 0;
        $count += preg_match_all('/player\.vimeo\.com/i', $html) ?: 0;
        $count += preg_match_all('/aparat\.com\/video/i', $html) ?: 0;
        return $count;
    }

    /** Detect FAQ section. */
    private function detect_faq(string $html): bool {
        if (preg_match('/"@type"\s*:\s*"FAQPage"/i', $html)) return true;
        if (preg_match('/class\s*=\s*["\'][^"\']*faq[^"\']*["\']/i', $html)) return true;
        if (preg_match('/<(h[1-4])[^>]*>[^<]*(سوالات متداول|پرسش.*پاسخ|FAQ)[^<]*<\/\1>/iu', $html)) return true;
        return false;
    }

    /** Extract top 10 frequent Persian words/bigrams (TF analysis). */
    private function extract_top_keywords(string $text): array {
        $text = mb_strtolower($text);
        // Remove common Persian stop words
        $stops = ['و','در','به','از','که','این','را','با','است','برای','آن','یک','تا','هم','ها','یا','شده','می','بر','اما','هر','نیز','اگر','شما','ما','من','خود','پس','بود','باید','دارد','همه','بین','هیچ','آنها','کنید','شود','دارند','کرد','کنند'];
        // Split into words
        $words = preg_split('/[\s\x{200C}\x{200B},.;:!?\-\(\)\[\]\/\\\\]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, function($w) use ($stops) {
            return mb_strlen($w) > 2 && !in_array($w, $stops) && !is_numeric($w);
        });

        // Count single words
        $freq = array_count_values(array_values($words));
        arsort($freq);
        $top_words = array_slice($freq, 0, 7, true);

        // Count bigrams
        $words_arr = array_values($words);
        $bigrams = [];
        for ($i = 0; $i < count($words_arr) - 1; $i++) {
            $bg = $words_arr[$i] . ' ' . $words_arr[$i + 1];
            $bigrams[$bg] = ($bigrams[$bg] ?? 0) + 1;
        }
        arsort($bigrams);
        $top_bigrams = array_slice($bigrams, 0, 3, true);

        // Combine: format as [{word, count}]
        $result = [];
        foreach ($top_words as $w => $c) {
            $result[] = ['word' => $w, 'count' => $c];
        }
        foreach ($top_bigrams as $w => $c) {
            if ($c >= 2) $result[] = ['word' => $w, 'count' => $c];
        }
        return array_slice($result, 0, 10);
    }

    /** Persian stop words shared across methods. */
    private function get_stop_words(): array {
        return ['و','در','به','از','که','این','را','با','است','برای','آن','یک','تا','هم','ها','یا','شده','می','بر','اما','هر','نیز','اگر','شما','ما','من','خود','پس','بود','باید','دارد','همه','بین','هیچ','آنها','کنید','شود','دارند','کرد','کنند','هستند','بودند','باشد','کنم','کنی','شدن','شوند','بوده','دارم','داری','دارید','داشت','داشته','خواهد','خواهند','آنچه','همان','چون','چنین','ولی','یعنی','حال','دیگر','وقتی','اینکه','هنوز','البته','بسیار'];
    }

    /** Tokenize text into clean words (filtered). */
    private function tokenize(string $text): array {
        $text = mb_strtolower($text);
        $stops = $this->get_stop_words();
        $words = preg_split('/[\s\x{200C}\x{200B},.;:!?\-\(\)\[\]\/\\\\]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($words, function($w) use ($stops) {
            return mb_strlen($w) > 2 && !in_array($w, $stops) && !is_numeric($w);
        }));
    }

    /** Extract n-grams (1-4) with frequency. */
    private function extract_ngrams(string $text): array {
        $words = $this->tokenize($text);
        $total = count($words);
        $result = ['unigrams' => [], 'bigrams' => [], 'trigrams' => [], 'fourgrams' => []];
        if ($total < 2) return $result;

        $freq = array_count_values($words);
        arsort($freq);
        foreach (array_slice($freq, 0, 15, true) as $w => $c) {
            $result['unigrams'][] = ['word' => $w, 'count' => $c, 'density' => round(($c / $total) * 100, 2)];
        }

        $bg = [];
        for ($i = 0; $i < $total - 1; $i++) {
            $key = $words[$i] . ' ' . $words[$i + 1];
            $bg[$key] = ($bg[$key] ?? 0) + 1;
        }
        arsort($bg);
        foreach (array_slice($bg, 0, 10, true) as $w => $c) {
            if ($c >= 2) $result['bigrams'][] = ['word' => $w, 'count' => $c, 'density' => round(($c / max(1, $total - 1)) * 100, 2)];
        }

        $tg = [];
        for ($i = 0; $i < $total - 2; $i++) {
            $key = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
            $tg[$key] = ($tg[$key] ?? 0) + 1;
        }
        arsort($tg);
        foreach (array_slice($tg, 0, 10, true) as $w => $c) {
            if ($c >= 2) $result['trigrams'][] = ['word' => $w, 'count' => $c, 'density' => round(($c / max(1, $total - 2)) * 100, 2)];
        }

        $fg = [];
        for ($i = 0; $i < $total - 3; $i++) {
            $key = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2] . ' ' . $words[$i + 3];
            $fg[$key] = ($fg[$key] ?? 0) + 1;
        }
        arsort($fg);
        foreach (array_slice($fg, 0, 10, true) as $w => $c) {
            if ($c >= 2) $result['fourgrams'][] = ['word' => $w, 'count' => $c, 'density' => round(($c / max(1, $total - 3)) * 100, 2)];
        }

        return $result;
    }

    /** Extract keywords from first and second paragraphs separately. */
    private function extract_paragraph_keywords(string $html): array {
        $result = ['first_paragraph' => [], 'second_paragraph' => []];
        if (!preg_match_all('/<p\b[^>]*>(.*?)<\/p>/si', $html, $pm)) return $result;
        $paragraphs = [];
        foreach ($pm[1] as $p) {
            $clean = trim(wp_strip_all_tags($p));
            $clean = html_entity_decode($clean, ENT_QUOTES, 'UTF-8');
            if (mb_strlen($clean) > 20) $paragraphs[] = $clean;
            if (count($paragraphs) >= 2) break;
        }
        if (isset($paragraphs[0])) {
            $words = $this->tokenize($paragraphs[0]);
            $freq = array_count_values($words);
            arsort($freq);
            foreach (array_slice($freq, 0, 10, true) as $w => $c) {
                $result['first_paragraph'][] = ['word' => $w, 'count' => $c];
            }
        }
        if (isset($paragraphs[1])) {
            $words = $this->tokenize($paragraphs[1]);
            $freq = array_count_values($words);
            arsort($freq);
            foreach (array_slice($freq, 0, 10, true) as $w => $c) {
                $result['second_paragraph'][] = ['word' => $w, 'count' => $c];
            }
        }
        return $result;
    }

    /** Content density analysis. */
    private function content_density_analysis(string $html, string $text, int $word_count): array {
        $html_size = strlen($html);
        $text_size = strlen($text);
        $text_to_html_ratio = $html_size > 0 ? round(($text_size / $html_size) * 100, 1) : 0;
        $body_html = '';
        if (preg_match('/<body[^>]*>(.*)<\/body>/si', $html, $bm)) $body_html = $bm[1];
        else $body_html = $html;
        $body_no_scripts = preg_replace('/<(script|style|svg|template|iframe)[^>]*>.*?<\/\1>/si', '', $body_html);
        $body_text = wp_strip_all_tags($body_no_scripts);
        $body_text_len = mb_strlen(trim($body_text));
        $useful_ratio = $body_text_len > 0 ? round(($word_count / max(1, PersianText::word_count($body_text))) * 100, 1) : 0;
        $para_count = preg_match_all('/<p\b[^>]*>(.*?)<\/p>/si', $html, $pp) ?: 0;
        $avg_para_length = 0;
        if ($para_count > 0) {
            $total_para_words = 0;
            foreach ($pp[1] as $p) {
                $clean = trim(wp_strip_all_tags($p));
                if (mb_strlen($clean) > 10) $total_para_words += PersianText::word_count($clean);
            }
            $avg_para_length = (int) round($total_para_words / $para_count);
        }
        return [
            'text_to_html_ratio' => $text_to_html_ratio,
            'useful_content_ratio' => min(100, $useful_ratio),
            'avg_paragraph_length' => $avg_para_length,
            'html_size_kb' => round($html_size / 1024, 1),
            'text_size_kb' => round($text_size / 1024, 1),
        ];
    }

    /** Word count per heading section breakdown. */
    private function word_count_per_section(string $html): array {
        $sections = [];
        // Split by h2 tags
        $parts = preg_split('/<h2\b[^>]*>/i', $html);
        if (count($parts) <= 1) return $sections;

        for ($i = 1; $i < count($parts) && $i <= 10; $i++) {
            $part = $parts[$i];
            // Get heading text (up to closing tag)
            $heading = '';
            if (preg_match('/^(.*?)<\/h2>/si', $part, $hm)) {
                $heading = trim(wp_strip_all_tags($hm[1]));
                $part = substr($part, strlen($hm[0]));
            }
            // Get text until next heading
            $part = preg_replace('/<h[1-6]\b.*$/si', '', $part);
            $part = preg_replace('/<(script|style|nav|footer|header|aside)[^>]*>.*?<\/\1>/si', ' ', $part);
            $clean_text = wp_strip_all_tags($part);
            $clean_text = html_entity_decode($clean_text, ENT_QUOTES, 'UTF-8');
            $wc = PersianText::word_count($clean_text);
            if ($heading) {
                $sections[] = ['heading' => mb_substr($heading, 0, 100), 'words' => $wc];
            }
        }
        return $sections;
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


    /** Analyze ~30 on-page SEO factors with scores and recommendations. */
    private function seo_factors_analysis(string $html, string $text, int $word_count, array $headings, int $images, int $img_no_alt, array $links, string $title, string $meta_desc, string $keyword, float $keyword_density, string $url): array {
        $factors = [];
        $kw_lower = $keyword ? mb_strtolower($keyword) : '';
        $title_lower = mb_strtolower($title);
        $text_lower = mb_strtolower($text);
        $url_lower = mb_strtolower($url);

        // 1. Title tag
        $title_len = mb_strlen($title); $t_score = 0; $t_tip = '';
        if ($title_len >= 30 && $title_len <= 60) $t_score = 100;
        elseif ($title_len > 0 && $title_len < 30) { $t_score = 50; $t_tip = 'عنوان کوتاه است.'; }
        elseif ($title_len > 60) { $t_score = 60; $t_tip = 'عنوان بلند است.'; }
        else { $t_score = 0; $t_tip = 'عنوان وجود ندارد!'; }
        if ($kw_lower && $t_score > 0) { if (mb_strpos($title_lower, $kw_lower) === false) { $t_score -= 20; $t_tip = 'کلمه کلیدی در عنوان نیست.'; } elseif (mb_strpos($title_lower, $kw_lower) <= 5) $t_score = min(100, $t_score + 10); }
        $factors[] = ['name' => 'title_optimization', 'label' => 'بهینه‌سازی عنوان (Title)', 'score' => max(0, $t_score), 'tip' => $t_tip];

        // 2. Meta description
        $meta_len = mb_strlen($meta_desc); $m_score = 0; $m_tip = '';
        if ($meta_len >= 120 && $meta_len <= 160) $m_score = 100;
        elseif ($meta_len >= 80 && $meta_len < 120) { $m_score = 70; $m_tip = 'متا کوتاه.'; }
        elseif ($meta_len > 160) { $m_score = 60; $m_tip = 'متا بلند.'; }
        elseif ($meta_len > 0) { $m_score = 40; $m_tip = 'متا خیلی کوتاه.'; }
        else { $m_score = 0; $m_tip = 'متا دسکریپشن وجود ندارد!'; }
        if ($kw_lower && $m_score > 0 && mb_strpos(mb_strtolower($meta_desc), $kw_lower) === false) { $m_score -= 15; $m_tip .= ' کلمه کلیدی در متا نیست.'; }
        $factors[] = ['name' => 'meta_description', 'label' => 'متا دسکریپشن', 'score' => max(0, $m_score), 'tip' => $m_tip];

        // 3. H1 tag
        $h1_score = 0; $h1_tip = '';
        if ($headings['h1'] === 1) $h1_score = 100;
        elseif ($headings['h1'] > 1) { $h1_score = 50; $h1_tip = 'بیش از یک H1.'; }
        else { $h1_score = 0; $h1_tip = 'H1 وجود ندارد!'; }
        if ($kw_lower && $h1_score > 0 && !empty($headings['h1_texts'])) { $in_h1 = false; foreach ($headings['h1_texts'] as $h) { if (mb_strpos(mb_strtolower($h), $kw_lower) !== false) { $in_h1 = true; break; } } if (!$in_h1) { $h1_score -= 20; $h1_tip = 'کلمه کلیدی در H1 نیست.'; } }
        $factors[] = ['name' => 'h1_tag', 'label' => 'تگ H1', 'score' => max(0, $h1_score), 'tip' => $h1_tip];

        // 4. URL structure
        $u_score = 80; $u_tip = ''; $path = wp_parse_url($url, PHP_URL_PATH) ?: '';
        if (strlen($path) > 80) { $u_score -= 30; $u_tip = 'URL بلند.'; }
        if ($kw_lower && mb_strpos($url_lower, $kw_lower) !== false) $u_score = min(100, $u_score + 20);
        elseif ($kw_lower) { $u_score -= 10; $u_tip .= ' کلمه در URL نیست.'; }
        $factors[] = ['name' => 'url_structure', 'label' => 'ساختار URL', 'score' => max(0, $u_score), 'tip' => trim($u_tip)];

        // 5. Keyword in first 100 words
        $f100_score = 50; $f100_tip = '';
        if ($kw_lower && $word_count > 0) { $first_words = implode(' ', array_slice(explode(' ', $text_lower), 0, 100)); $f100_score = mb_strpos($first_words, $kw_lower) !== false ? 100 : 0; if ($f100_score === 0) $f100_tip = 'کلمه کلیدی در ۱۰۰ کلمه اول نیست.'; }
        $factors[] = ['name' => 'keyword_first_100', 'label' => 'کلمه کلیدی در ۱۰۰ کلمه اول', 'score' => $f100_score, 'tip' => $f100_tip];

        // 6. Keyword density
        $d_score = 50; $d_tip = '';
        if ($kw_lower) { if ($keyword_density >= 0.5 && $keyword_density <= 2.5) $d_score = 100; elseif ($keyword_density > 2.5 && $keyword_density <= 3.5) { $d_score = 60; $d_tip = 'تراکم بالا.'; } elseif ($keyword_density > 3.5) { $d_score = 20; $d_tip = 'تراکم بسیار بالا!'; } elseif ($keyword_density > 0) { $d_score = 50; $d_tip = 'تراکم کم.'; } else { $d_score = 0; $d_tip = 'کلمه استفاده نشده!'; } }
        $factors[] = ['name' => 'keyword_density', 'label' => 'تراکم کلمه کلیدی', 'score' => $d_score, 'tip' => $d_tip];

        // 7. Content length
        $cl_score = 10; $cl_tip = '';
        if ($word_count >= 2000) $cl_score = 100; elseif ($word_count >= 1200) $cl_score = 85; elseif ($word_count >= 800) $cl_score = 70; elseif ($word_count >= 500) { $cl_score = 50; $cl_tip = 'محتوا کوتاه.'; } elseif ($word_count >= 300) { $cl_score = 30; $cl_tip = 'محتوا کوتاه.'; } else $cl_tip = 'محتوا بسیار کوتاه.';
        $factors[] = ['name' => 'content_length', 'label' => 'طول محتوا', 'score' => $cl_score, 'tip' => $cl_tip];

        // 8. Images
        $i_score = 0; $i_tip = '';
        if ($images >= 3 && $img_no_alt === 0) $i_score = 100; elseif ($images >= 3) { $i_score = 70; $i_tip = $img_no_alt . ' تصویر بدون alt.'; } elseif ($images >= 1) { $i_score = 50; $i_tip = 'تصاویر کم.'; } else { $i_score = 0; $i_tip = 'تصویری ندارد!'; }
        $factors[] = ['name' => 'image_optimization', 'label' => 'بهینه‌سازی تصاویر', 'score' => $i_score, 'tip' => $i_tip];

        // 9-10. Links
        $il_score = $links['internal'] >= 5 ? 100 : ($links['internal'] >= 3 ? 70 : ($links['internal'] >= 1 ? 40 : 0));
        $factors[] = ['name' => 'internal_links', 'label' => 'لینک‌سازی داخلی', 'score' => $il_score, 'tip' => $il_score < 70 ? 'لینک داخلی کم.' : ''];
        $el_score = ($links['external'] >= 1 && $links['external'] <= 5) ? 100 : ($links['external'] > 5 ? 70 : 30);
        $factors[] = ['name' => 'external_links', 'label' => 'لینک خارجی (استناد)', 'score' => $el_score, 'tip' => $links['external'] === 0 ? 'لینک خارجی ندارد.' : ''];

        // 11. Page speed
        $html_kb = strlen($html) / 1024;
        $sp_score = $html_kb < 100 ? 100 : ($html_kb < 200 ? 80 : ($html_kb < 500 ? 50 : 20));
        $factors[] = ['name' => 'page_speed', 'label' => 'سرعت صفحه (حجم HTML)', 'score' => $sp_score, 'tip' => $sp_score < 80 ? 'HTML سنگین.' : ''];

        // 12. Viewport
        $viewport = (bool) preg_match('/<meta[^>]+name\s*=\s*["\']viewport["\']/i', $html);
        $factors[] = ['name' => 'mobile_friendly', 'label' => 'سازگاری موبایل', 'score' => $viewport ? 100 : 0, 'tip' => $viewport ? '' : 'تگ viewport ندارد!'];

        // 13. Schema
        $schema_count = preg_match_all('/"@type"/i', $html) ?: 0;
        $factors[] = ['name' => 'schema_markup', 'label' => 'Schema', 'score' => $schema_count >= 2 ? 100 : ($schema_count === 1 ? 70 : 0), 'tip' => $schema_count === 0 ? 'Schema ندارد.' : ''];

        // 14. OG
        $og_count = preg_match_all('/<meta[^>]+property\s*=\s*["\']og:/i', $html) ?: 0;
        $factors[] = ['name' => 'open_graph', 'label' => 'Open Graph', 'score' => $og_count >= 4 ? 100 : ($og_count >= 2 ? 70 : ($og_count >= 1 ? 40 : 0)), 'tip' => $og_count === 0 ? 'OG ندارد.' : ''];

        // 15. Canonical
        $has_can = (bool) preg_match('/<link[^>]+rel\s*=\s*["\']canonical["\']/i', $html);
        $factors[] = ['name' => 'canonical_tag', 'label' => 'تگ Canonical', 'score' => $has_can ? 100 : 0, 'tip' => $has_can ? '' : 'Canonical ندارد.'];

        // 16. Robots
        $robots = $this->extract_robots_meta($html);
        $r_score = 100; $r_tip = '';
        if (strpos($robots, 'noindex') !== false) { $r_score = 0; $r_tip = 'noindex دارد!'; } elseif (strpos($robots, 'nofollow') !== false) { $r_score = 50; $r_tip = 'nofollow دارد.'; }
        $factors[] = ['name' => 'robots_directive', 'label' => 'دستور Robots', 'score' => $r_score, 'tip' => $r_tip];

        // 17. Language
        $has_lang = (bool) preg_match('/<html[^>]+lang\s*=/i', $html);
        $factors[] = ['name' => 'language_declaration', 'label' => 'اعلام زبان', 'score' => $has_lang ? 100 : 0, 'tip' => $has_lang ? '' : 'اعلام زبان ندارد.'];

        // 18. Freshness
        $has_date = (bool) (preg_match('/\b(202[3-6])\b/', $html) || preg_match('/"dateModified"|"datePublished"/i', $html));
        $factors[] = ['name' => 'content_freshness', 'label' => 'تازگی محتوا', 'score' => $has_date ? 100 : 30, 'tip' => $has_date ? '' : 'نشانه تاریخ یافت نشد.'];

        // 19. FAQ
        $factors[] = ['name' => 'faq_section', 'label' => 'بخش FAQ', 'score' => $this->detect_faq($html) ? 100 : 0, 'tip' => $this->detect_faq($html) ? '' : 'FAQ ندارد.'];

        // 20. TOC
        $has_toc = (bool) preg_match('/table.of.content|ez-toc|lwptoc/i', $html);
        $factors[] = ['name' => 'table_of_contents', 'label' => 'فهرست مطالب (TOC)', 'score' => $has_toc ? 100 : 0, 'tip' => $has_toc ? '' : 'فهرست مطالب ندارد.'];

        // 21. Breadcrumb
        $has_bread = (bool) preg_match('/breadcrumb|BreadcrumbList/i', $html);
        $factors[] = ['name' => 'breadcrumb', 'label' => 'مسیرنما (Breadcrumb)', 'score' => $has_bread ? 100 : 0, 'tip' => $has_bread ? '' : 'مسیرنما ندارد.'];

        // 22. Video
        $vid_count = $this->count_videos($html);
        $factors[] = ['name' => 'video_multimedia', 'label' => 'ویدیو/مولتی‌مدیا', 'score' => $vid_count >= 1 ? 100 : 0, 'tip' => $vid_count < 1 ? 'ویدیو ندارد.' : ''];

        // 23. Readability
        $sentences = max(1, preg_match_all('/[.!?]/u', $text) ?: 1);
        $avg_sent = $word_count > 0 ? round($word_count / $sentences) : 0;
        $rd_score = 70;
        if ($avg_sent >= 10 && $avg_sent <= 25) $rd_score = 100; elseif ($avg_sent > 25 && $avg_sent <= 35) $rd_score = 60; elseif ($avg_sent > 35) $rd_score = 30;
        $factors[] = ['name' => 'readability', 'label' => 'خوانایی محتوا', 'score' => $rd_score, 'tip' => $rd_score < 70 ? 'جملات بلند.' : ''];

        // 24. Heading hierarchy
        $hh_score = 100;
        if ($headings['h1'] === 0) $hh_score -= 40;
        if ($headings['h2'] === 0) $hh_score -= 30;
        if ($headings['h3'] > 0 && $headings['h2'] === 0) $hh_score -= 20;
        $factors[] = ['name' => 'heading_hierarchy', 'label' => 'سلسله‌مراتب هدینگ', 'score' => max(0, $hh_score), 'tip' => $hh_score < 100 ? 'سلسله‌مراتب هدینگ ناقص.' : ''];

        // 25. Paragraphs
        $para_count = preg_match_all('/<p\b[^>]*>/i', $html) ?: 0;
        $factors[] = ['name' => 'paragraph_structure', 'label' => 'ساختار پاراگراف', 'score' => $para_count >= 5 ? 100 : ($para_count >= 3 ? 70 : ($para_count >= 1 ? 40 : 0)), 'tip' => $para_count < 3 ? 'پاراگراف کم.' : ''];

        // 26. Lists
        $list_count = preg_match_all('/<(ul|ol)\b/i', $html) ?: 0;
        $factors[] = ['name' => 'list_usage', 'label' => 'استفاده از لیست', 'score' => $list_count >= 2 ? 100 : ($list_count === 1 ? 60 : 0), 'tip' => $list_count < 1 ? 'لیست ندارد.' : ''];

        // 27. Bold
        $bold_count = preg_match_all('/<(strong|b)\b/i', $html) ?: 0;
        $factors[] = ['name' => 'bold_emphasis', 'label' => 'برجسته‌سازی (Bold)', 'score' => $bold_count >= 3 ? 100 : ($bold_count >= 1 ? 60 : 0), 'tip' => $bold_count < 1 ? 'از bold استفاده نشده.' : ''];

        // 28. Content uniqueness
        $nav_size = 0;
        if (preg_match_all('/<(nav|footer|sidebar|header)[^>]*>.*?<\/\1>/si', $html, $bp)) $nav_size = mb_strlen(implode('', $bp[0]));
        $bp_ratio = strlen($html) > 0 ? round(($nav_size / strlen($html)) * 100) : 0;
        $uniq_score = $bp_ratio < 30 ? 100 : ($bp_ratio < 50 ? 60 : 20);
        $factors[] = ['name' => 'content_uniqueness', 'label' => 'یکتایی محتوا', 'score' => $uniq_score, 'tip' => $uniq_score < 60 ? 'نسبت boilerplate بالا.' : ''];

        // 29. Social
        $social = (bool) preg_match('/share|social|telegram|whatsapp|twitter|facebook/i', $html);
        $factors[] = ['name' => 'social_sharing', 'label' => 'دکمه‌های اشتراک‌گذاری', 'score' => $social ? 100 : 0, 'tip' => $social ? '' : 'دکمه اشتراک ندارد.'];

        // 30. LSI
        $lsi_score = 50; $lsi_tip = '';
        if ($kw_lower && $word_count > 100) { $unique_words = count(array_unique($this->tokenize($text))); $ratio = $unique_words / max(1, $word_count); if ($ratio >= 0.4) $lsi_score = 100; elseif ($ratio >= 0.25) $lsi_score = 70; else { $lsi_score = 30; $lsi_tip = 'تنوع واژگانی کم.'; } }
        $factors[] = ['name' => 'lsi_keywords', 'label' => 'تنوع معنایی (LSI)', 'score' => $lsi_score, 'tip' => $lsi_tip];

        // Overall
        $total = 0; foreach ($factors as $f) $total += $f['score'];
        $overall = count($factors) > 0 ? (int) round($total / count($factors)) : 0;
        return ['factors' => $factors, 'overall_score' => $overall];
    }

    /** Build SEO strengths list. */
    private function build_strengths(int $wc, array $h, int $img, int $tables, bool $faq, int $videos, array $links, array $schema, string $title, string $meta, string $kw, float $density): array {
        $s = [];
        if ($wc >= 1500) $s[] = 'محتوای جامع (' . PersianText::format_number($wc) . ' کلمه)';
        elseif ($wc >= 800) $s[] = 'طول محتوا مناسب (' . PersianText::format_number($wc) . ' کلمه)';
        if ($h['h2'] >= 3) $s[] = 'ساختار هدینگ خوب (' . $h['h2'] . ' H2)';
        if ($img >= 3) $s[] = 'تصاویر مناسب (' . $img . ' عدد)';
        if ($tables > 0) $s[] = 'استفاده از جدول (ساختارمند)';
        if ($faq) $s[] = 'بخش FAQ دارد (Featured Snippet)';
        if ($videos > 0) $s[] = 'ویدیو دارد (جذابیت بالا)';
        if ($links['internal'] >= 5) $s[] = 'لینک داخلی قوی (' . $links['internal'] . ')';
        if ($links['external'] >= 1 && $links['external'] <= 5) $s[] = 'استناد مناسب (' . $links['external'] . ' لینک خارجی)';
        if (!empty($schema)) $s[] = 'Schema: ' . implode(', ', array_slice($schema, 0, 3));
        if (mb_strlen($meta) >= 120 && mb_strlen($meta) <= 160) $s[] = 'متا دسکریپشن بهینه';
        if (mb_strlen($title) >= 30 && mb_strlen($title) <= 65) $s[] = 'عنوان بهینه';
        if ($kw && $density >= 0.5 && $density <= 2.5) $s[] = 'تراکم کلمه کلیدی مناسب (' . $density . '%)';
        return $s;
    }

    /** Build SEO weaknesses list. */
    private function build_weaknesses(int $wc, array $h, int $img, int $noalt, int $tables, bool $faq, int $videos, array $links, array $schema, string $title, string $meta, string $kw, float $density): array {
        $w = [];
        if ($wc < 300) $w[] = 'محتوا بسیار کوتاه (' . PersianText::format_number($wc) . ' کلمه)';
        elseif ($wc < 800) $w[] = 'محتوا نسبتاً کوتاه. رقبای قوی ۱۵۰۰+ کلمه دارند.';
        if ($h['h2'] < 2) $w[] = 'هدینگ H2 کم (' . $h['h2'] . '). ساختار ضعیف.';
        if ($img < 2) $w[] = 'تصاویر کم. محتوای بصری ضعیف.';
        if ($noalt > 0) $w[] = $noalt . ' تصویر بدون alt (مشکل سئو).';
        if (!$faq) $w[] = 'FAQ ندارد. فرصت Featured Snippet از دست رفته.';
        if (empty($schema)) $w[] = 'Schema ندارد. فرصت Rich Snippet از دست رفته.';
        if ($links['internal'] < 3) $w[] = 'لینک داخلی کم (' . $links['internal'] . '). سیلو ضعیف.';
        if ($links['external'] === 0) $w[] = 'لینک خارجی/استناد ندارد.';
        if (empty($meta)) $w[] = 'متا دسکریپشن ندارد!';
        elseif (mb_strlen($meta) < 100) $w[] = 'متا کوتاه (' . mb_strlen($meta) . ' کاراکتر).';
        if (mb_strlen($title) > 65) $w[] = 'عنوان بلند (' . mb_strlen($title) . ' کاراکتر). گوگل کوتاه می‌کند.';
        if ($kw) {
            if ($density < 0.5) $w[] = 'تراکم کلمه کلیدی خیلی کم (' . $density . '%)';
            elseif ($density > 3) $w[] = 'تراکم زیاد (' . $density . '%). خطر اسپم!';
        }
        return $w;
    }
}
