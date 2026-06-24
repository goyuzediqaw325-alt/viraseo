<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Admin\Dashboard;
use ViraSEO\Utils\{JalaliDate, PersianText};

/**
 * Core Web Vitals Monitor [🟢 مستقل]
 *
 * Measures real Persian-page performance via Google PageSpeed Insights API
 * (free; an optional API key raises the rate limit). Prefers field data (CrUX,
 * real Chrome users) and falls back to lab data (Lighthouse). All audit titles
 * are requested with locale=fa so improvement suggestions are already in Persian.
 *
 * Why CWV matters for SEO 2026: Core Web Vitals are a confirmed Google ranking
 * signal and directly affect crawl efficiency and conversions on mobile-heavy
 * Iranian audiences.
 */
class CoreWebVitals {

    private const PSI = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    // Core Web Vitals thresholds (Google official): [good_max, poor_min]
    private const TH_LCP = [2500, 4000];  // ms
    private const TH_INP = [200, 500];    // ms
    private const TH_CLS = [0.10, 0.25];  // unitless

    public function __construct() {
        add_action('wp_ajax_viraseo_cwv_check', [$this, 'ajax_check']);
        add_action('wp_ajax_viraseo_cwv_batch', [$this, 'ajax_batch']);
        add_action('wp_ajax_viraseo_cwv_list', [$this, 'ajax_list']);
    }

    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'viraseo_cwv_monitor';
    }

    /** Classify a single metric value into good / ni (needs improvement) / poor. */
    private function classify(float $value, array $th): string {
        if ($value <= $th[0]) return 'good';
        if ($value <= $th[1]) return 'ni';
        return 'poor';
    }

    /** Worst of the three CWV verdicts decides the overall verdict. */
    private function overall(string ...$verdicts): string {
        if (in_array('poor', $verdicts, true)) return 'poor';
        if (in_array('ni', $verdicts, true)) return 'ni';
        return 'good';
    }

    /**
     * Call PSI for one URL+strategy and normalize the response into our schema.
     * Returns ['error'=>msg] on failure.
     */
    private function measure(string $url, string $strategy): array {
        $args = [
            'url'      => $url,
            'strategy' => $strategy,
            'category' => 'performance',
            'locale'   => 'fa', // Persian audit titles/descriptions out of the box
        ];
        $key = Dashboard::get('psi_api_key');
        if ($key) $args['key'] = $key;

        $endpoint = self::PSI . '?' . http_build_query($args);

        // Use cURL proxy for PSI if configured (Iran hosts can't reach Google APIs directly)
        if (!empty(Dashboard::get('psi_use_proxy')) && Dashboard::get('ai_curl_proxy')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_PROXY => Dashboard::get('ai_curl_proxy'),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $px = Dashboard::get('ai_curl_proxy');
            if (strpos($px, '@') !== false) curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            $rawBody = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($err) return ['error' => 'خطا در اتصال به PageSpeed (از طریق پروکسی): ' . $err];
            $body = json_decode($rawBody, true);
        } else {
            $resp = wp_remote_get($endpoint, ['timeout' => 60]);
            if (is_wp_error($resp)) {
                return ['error' => 'خطا در اتصال به PageSpeed: ' . $resp->get_error_message()];
            }
            $code = (int) wp_remote_retrieve_response_code($resp);
            $body = json_decode(wp_remote_retrieve_body($resp), true);
        }
        if ($code === 429) return ['error' => 'محدودیت نرخ PageSpeed. کمی صبر کنید یا کلید PSI رایگان را در تنظیمات وارد کنید.'];
        if ($code >= 400) return ['error' => $body['error']['message'] ?? "خطای PageSpeed (HTTP {$code})"];
        if (empty($body['lighthouseResult'])) return ['error' => 'پاسخ نامعتبر از PageSpeed.'];

        $lh = $body['lighthouseResult'];
        $audits = $lh['audits'] ?? [];
        $perf = (int) round((float)($lh['categories']['performance']['score'] ?? 0) * 100);

        // ---- Field data (CrUX, real users) preferred; fall back to lab ----
        $field = $body['loadingExperience']['metrics'] ?? [];
        $source = 'lab';
        if (!empty($field)) {
            $source = 'field';
            $lcp = (int) ($field['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ?? 0);
            $inp = (int) ($field['INTERACTION_TO_NEXT_PAINT']['percentile']
                        ?? $field['EXPERIMENTAL_INTERACTION_TO_NEXT_PAINT']['percentile'] ?? 0);
            $clsRaw = (float) ($field['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] ?? 0);
            $cls = $clsRaw / 100; // CrUX returns CLS x100
            $fcp = (int) ($field['FIRST_CONTENTFUL_PAINT_MS']['percentile'] ?? 0);
        } else {
            $lcp = (int) round((float)($audits['largest-contentful-paint']['numericValue'] ?? 0));
            // Lab proxy for INP is Total Blocking Time
            $inp = (int) round((float)($audits['total-blocking-time']['numericValue'] ?? 0));
            $cls = round((float)($audits['cumulative-layout-shift']['numericValue'] ?? 0), 3);
            $fcp = (int) round((float)($audits['first-contentful-paint']['numericValue'] ?? 0));
        }
        $ttfb = (int) round((float)($audits['server-response-time']['numericValue'] ?? 0));

        $vLcp = $this->classify($lcp, self::TH_LCP);
        $vInp = $this->classify($inp, self::TH_INP);
        $vCls = $this->classify($cls, self::TH_CLS);
        $verdict = $this->overall($vLcp, $vInp, $vCls);

        $suggestions = $this->extract_suggestions($audits, $ttfb);

        return [
            'url'         => $url,
            'strategy'    => $strategy,
            'data_source' => $source,
            'perf_score'  => $perf,
            'lcp'         => $lcp,
            'inp'         => $inp,
            'cls'         => $cls,
            'fcp'         => $fcp,
            'ttfb'        => $ttfb,
            'verdict'     => $verdict,
            'metric_verdicts' => ['lcp' => $vLcp, 'inp' => $vInp, 'cls' => $vCls],
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Pull the highest-impact failing audits as Persian recommendations.
     * Combines Lighthouse "opportunities" (with estimated savings) and a curated
     * set of diagnostics that matter most for Iranian hosts (TTFB, compression, caching).
     */
    private function extract_suggestions(array $audits, int $ttfb): array {
        $out = [];

        // 1) Opportunities (sorted by potential time savings)
        $opps = [];
        foreach ($audits as $id => $a) {
            $score = $a['score'] ?? null;
            $type = $a['details']['type'] ?? '';
            if ($type === 'opportunity' && $score !== null && $score < 0.9) {
                $saveMs = (int) round((float)($a['details']['overallSavingsMs'] ?? ($a['numericValue'] ?? 0)));
                $opps[] = [
                    'title'  => $a['title'] ?? $id,
                    'save'   => $saveMs,
                ];
            }
        }
        usort($opps, fn($a, $b) => $b['save'] <=> $a['save']);
        foreach (array_slice($opps, 0, 8) as $o) {
            $save = $o['save'] >= 100 ? ' (صرفه‌جویی ~' . PersianText::format_number((int)round($o['save']/1000, 1)) . ' ثانیه)' : '';
            $out[] = $o['title'] . $save;
        }

        // 2) High-value diagnostics for Persian/Iran hosts
        $diag = ['server-response-time', 'uses-text-compression', 'uses-long-cache-ttl',
                 'render-blocking-resources', 'font-display', 'modern-image-formats',
                 'unminified-css', 'unminified-javascript', 'dom-size', 'redirects'];
        foreach ($diag as $id) {
            if (isset($audits[$id]) && ($audits[$id]['score'] ?? 1) !== null && ($audits[$id]['score'] ?? 1) < 0.9) {
                $title = $audits[$id]['title'] ?? $id;
                if (!in_array($title, $out, true)) $out[] = $title;
            }
        }

        // 3) Host-specific hint when TTFB is high (common on Iranian shared hosting)
        if ($ttfb > 800) {
            $out[] = 'زمان پاسخ سرور (TTFB) شما ' . PersianText::format_number($ttfb) . ' میلی‌ثانیه است؛ این معمولاً مشکل هاست/کش است. کش کامل صفحه (مثل LiteSpeed Cache یا WP Rocket) فعال کنید یا هاست قوی‌تری بگیرید.';
        }

        return array_slice(array_values(array_unique($out)), 0, 10);
    }

    /** Persist a measurement row (upsert by url+strategy). */
    private function save(array $m, ?int $post_id): void {
        global $wpdb;
        $t = $this->table();
        $hash = md5($m['url']);
        $data = [
            'post_id'     => $post_id ?: null,
            'url'         => $m['url'],
            'url_hash'    => $hash,
            'strategy'    => $m['strategy'],
            'data_source' => $m['data_source'],
            'perf_score'  => $m['perf_score'],
            'lcp'         => $m['lcp'],
            'inp'         => $m['inp'],
            'cls'         => $m['cls'],
            'fcp'         => $m['fcp'],
            'ttfb'        => $m['ttfb'],
            'verdict'     => $m['verdict'],
            'suggestions' => wp_json_encode($m['suggestions'], JSON_UNESCAPED_UNICODE),
            'checked_at'  => current_time('mysql'),
        ];
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE url_hash=%s AND strategy=%s", $hash, $m['strategy']));
        if ($exists) $wpdb->update($t, $data, ['id' => (int)$exists]);
        else $wpdb->insert($t, $data);
    }

    /** Format a stored/fresh measurement for the UI (Persian numbers + labels). */
    private function present(array $m): array {
        $lab = ['good' => 'خوب', 'ni' => 'نیازمند بهبود', 'poor' => 'ضعیف', 'unknown' => 'نامشخص'];
        $mv = $m['metric_verdicts'] ?? ['lcp'=>'unknown','inp'=>'unknown','cls'=>'unknown'];
        return [
            'url'         => $m['url'],
            'strategy'    => $m['strategy'],
            'source'      => $m['data_source'] === 'field' ? 'داده‌ی واقعی کاربران (CrUX)' : 'داده‌ی آزمایشگاهی (Lighthouse)',
            'perf'        => PersianText::format_number((int)$m['perf_score']),
            'lcp'         => PersianText::format_number(round($m['lcp']/1000, 2)) . 'ث',
            'inp'         => PersianText::format_number((int)$m['inp']) . 'ms',
            'cls'         => JalaliDate::to_fa(number_format((float)$m['cls'], 3)),
            'ttfb'        => PersianText::format_number((int)$m['ttfb']) . 'ms',
            'verdict'     => $m['verdict'],
            'verdict_fa'  => $lab[$m['verdict']] ?? '—',
            'v_lcp'       => $mv['lcp'],
            'v_inp'       => $mv['inp'],
            'v_cls'       => $mv['cls'],
            'suggestions' => $m['suggestions'] ?? [],
        ];
    }

    /** AJAX: measure a single URL (mobile + desktop optional). */
    public function ajax_check(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $url = esc_url_raw($_POST['url'] ?? '');
        if (!$url) wp_send_json_error('آدرس نامعتبر است.');
        $strategy = ($_POST['strategy'] ?? 'mobile') === 'desktop' ? 'desktop' : 'mobile';

        $m = $this->measure($url, $strategy);
        if (!empty($m['error'])) wp_send_json_error($m['error']);
        $this->save($m, url_to_postid($url) ?: null);
        wp_send_json_success($this->present($m));
    }

    /**
     * AJAX: measure a batch of recent published pages. Capped to respect the PSI
     * quota; mobile strategy (Google indexes mobile-first).
     */
    public function ajax_batch(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $limit = max(1, min(15, absint($_POST['limit'] ?? 5)));
        $strategy = ($_POST['strategy'] ?? 'mobile') === 'desktop' ? 'desktop' : 'mobile';

        $ids = get_posts([
            'post_type'   => TargetKeywords::public_types(),
            'post_status' => 'publish',
            'numberposts' => $limit,
            'orderby'     => 'modified',
            'order'       => 'DESC',
            'fields'      => 'ids',
        ]);

        $rows = []; $good = 0; $poor = 0; $errors = 0;
        foreach ($ids as $pid) {
            if (TargetKeywords::is_excluded((int)$pid)) continue;
            $url = get_permalink($pid);
            $m = $this->measure($url, $strategy);
            if (!empty($m['error'])) { $errors++; continue; }
            $this->save($m, (int)$pid);
            $p = $this->present($m);
            $p['title'] = get_the_title($pid) ?: $url;
            $p['edit']  = get_edit_post_link($pid, 'raw');
            if ($m['verdict'] === 'good') $good++;
            if ($m['verdict'] === 'poor') $poor++;
            $rows[] = $p;
            usleep(300000); // be gentle on the API
        }
        wp_send_json_success([
            'rows'   => $rows,
            'good'   => $good,
            'poor'   => $poor,
            'total'  => count($rows),
            'errors' => $errors,
        ]);
    }

    /** AJAX: return previously stored measurements (no API calls). */
    public function ajax_list(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        global $wpdb;
        $t = $this->table();
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) wp_send_json_success(['rows' => []]);
        $strategy = ($_POST['strategy'] ?? 'mobile') === 'desktop' ? 'desktop' : 'mobile';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t} WHERE strategy=%s ORDER BY FIELD(verdict,'poor','ni','good','unknown'), perf_score ASC LIMIT 300",
            $strategy
        ));
        $out = [];
        foreach ($rows ?: [] as $r) {
            $m = [
                'url' => $r->url, 'strategy' => $r->strategy, 'data_source' => $r->data_source,
                'perf_score' => $r->perf_score, 'lcp' => $r->lcp, 'inp' => $r->inp, 'cls' => $r->cls,
                'fcp' => $r->fcp, 'ttfb' => $r->ttfb, 'verdict' => $r->verdict,
                'suggestions' => json_decode($r->suggestions ?: '[]', true) ?: [],
                'metric_verdicts' => [
                    'lcp' => $this->classify((float)$r->lcp, self::TH_LCP),
                    'inp' => $this->classify((float)$r->inp, self::TH_INP),
                    'cls' => $this->classify((float)$r->cls, self::TH_CLS),
                ],
            ];
            $p = $this->present($m);
            $pid = (int)$r->post_id;
            if ($pid) { $p['title'] = get_the_title($pid) ?: $r->url; $p['edit'] = get_edit_post_link($pid, 'raw'); }
            $p['checked'] = JalaliDate::format($r->checked_at, 'relative');
            $out[] = $p;
        }
        wp_send_json_success(['rows' => $out]);
    }

    /** Used by ActionPlan: how many monitored pages have poor CWV. */
    public static function poor_count(): int {
        global $wpdb;
        $t = $wpdb->prefix . 'viraseo_cwv_monitor';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) return 0;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE verdict='poor'");
    }
}
