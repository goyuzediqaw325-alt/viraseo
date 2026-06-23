<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Api\AiClient;
use ViraSEO\Utils\{JalaliDate, PersianText};

/**
 * Keyword Cannibalization — AI analysis + automatic consolidation (merge) [🟢 مستقل]
 *
 * Detects when several of your own pages compete for the same Persian query (which
 * splits ranking signals and hurts all of them), then lets you resolve it three ways:
 *   1. canonical  — point the weaker page's canonical at the stronger one (safe, reversible)
 *   2. redirect   — 301 the weaker page to the stronger one (full consolidation)
 *   3. merge      — append the weaker page's content into the stronger one, then 301
 *
 * Detection reuses the shared viraseo_cannibalization table. Redirects are served
 * from a lightweight option-backed map so we don't depend on Rank Math's redirect module.
 */
class Cannibalization {

    private const REDIRECT_OPT = 'viraseo_merge_redirects';   // [ source_path => target_url ]
    private const CANONICAL_OPT = 'viraseo_merge_canonicals'; // [ post_id => target_url ]

    public function __construct() {
        add_action('wp_ajax_viraseo_cannibal_detect', [$this, 'ajax_detect']);
        add_action('wp_ajax_viraseo_cannibal_list', [$this, 'ajax_list']);
        add_action('wp_ajax_viraseo_cannibal_ai', [$this, 'ajax_ai']);
        add_action('wp_ajax_viraseo_cannibal_merge', [$this, 'ajax_merge']);
        add_action('wp_ajax_viraseo_cannibal_resolve', [$this, 'ajax_resolve']);

        // Serve consolidation actions on the front-end
        add_action('template_redirect', [$this, 'maybe_redirect'], 1);
        add_action('wp_head', [$this, 'maybe_canonical'], 1);
    }

    private function ct(): string { global $wpdb; return $wpdb->prefix . 'viraseo_cannibalization'; }
    private function kt(): string { global $wpdb; return $wpdb->prefix . 'viraseo_gsc_keywords'; }

    /* ------------------------------------------------------------------ */
    /* Detection                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Detect cannibalization from GSC keyword data. A conflict = one query ranking
     * across 2+ distinct URLs with meaningful impressions. Severity scales with how
     * close the two positions are (closer = more harmful signal splitting).
     */
    public function ajax_detect(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $kt = $this->kt(); $ct = $this->ct();
        if ($wpdb->get_var("SHOW TABLES LIKE '{$kt}'") !== $kt) wp_send_json_error('ابتدا داده‌های سرچ کنسول را همگام‌سازی کنید.');

        // Aggregate per keyword+page (latest values), then group by keyword.
        $rows = $wpdb->get_results(
            "SELECT keyword, keyword_hash, page_url, AVG(position) pos, SUM(impressions) imp, SUM(clicks) clk
             FROM {$kt}
             WHERE impressions >= 5
             GROUP BY keyword_hash, page_url_hash
             ORDER BY keyword_hash, imp DESC"
        );

        $byKw = [];
        foreach ($rows as $r) {
            $byKw[$r->keyword_hash]['keyword'] = $r->keyword;
            $byKw[$r->keyword_hash]['pages'][] = [
                'url' => $r->page_url, 'pos' => (float)$r->pos,
                'imp' => (int)$r->imp, 'clk' => (int)$r->clk,
            ];
        }

        $ins = 0; $skipped = 0;
        foreach ($byKw as $kh => $info) {
            if (count($info['pages'] ?? []) < 2) continue;
            // already tracked & still open?
            if ($wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$ct} WHERE keyword_hash=%s AND status='detected'", $kh))) { $skipped++; continue; }

            // Top two contenders by impressions
            usort($info['pages'], fn($a, $b) => $b['imp'] <=> $a['imp']);
            $a = $info['pages'][0]; $b = $info['pages'][1];
            // Skip trivial cases: the second page barely shows up
            if ($b['imp'] < 5) continue;

            $diff = abs($a['pos'] - $b['pos']);
            $severity = $diff <= 3 ? 'critical' : ($diff <= 7 ? 'warning' : 'info');

            $wpdb->insert($ct, [
                'keyword' => $info['keyword'], 'keyword_hash' => $kh,
                'page_url_1' => $a['url'], 'position_1' => round($a['pos'], 1), 'impressions_1' => $a['imp'],
                'page_url_2' => $b['url'], 'position_2' => round($b['pos'], 1), 'impressions_2' => $b['imp'],
                'severity' => $severity,
                'recommended_action' => $severity === 'critical' ? 'merge' : ($severity === 'warning' ? 'canonical' : 'differentiate'),
            ]);
            $ins++;
        }
        wp_send_json_success(['detected' => $ins, 'skipped' => $skipped]);
    }

    /* ------------------------------------------------------------------ */
    /* Listing                                                            */
    /* ------------------------------------------------------------------ */

    public function ajax_list(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        global $wpdb;
        $ct = $this->ct();
        $status = in_array($_POST['status'] ?? 'detected', ['detected', 'resolved', 'ignored'], true) ? $_POST['status'] : 'detected';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$ct} WHERE status=%s ORDER BY FIELD(severity,'critical','warning','info'), impressions_1 DESC LIMIT 200",
            $status
        ));
        $sevFa = ['critical' => 'بحرانی', 'warning' => 'هشدار', 'info' => 'جزئی'];
        $actFa = ['merge' => 'ادغام/ریدایرکت', 'canonical' => 'کانونیکال', 'differentiate' => 'تفکیک محتوا'];
        $data = array_map(function ($r) use ($sevFa, $actFa) {
            $p1 = url_to_postid($r->page_url_1); $p2 = url_to_postid($r->page_url_2);
            // Stronger = better (lower) position; that's the recommended winner.
            $winner = ((float)$r->position_1 <= (float)$r->position_2) ? 1 : 2;
            return [
                'id' => (int)$r->id,
                'keyword' => $r->keyword,
                'severity' => $r->severity,
                'severity_fa' => $sevFa[$r->severity] ?? $r->severity,
                'action' => $r->recommended_action,
                'action_fa' => $actFa[$r->recommended_action] ?? $r->recommended_action,
                'winner' => $winner,
                'page_1' => [
                    'url' => $r->page_url_1, 'pid' => $p1 ?: 0,
                    'title' => $p1 ? (get_the_title($p1) ?: $r->page_url_1) : $r->page_url_1,
                    'pos' => JalaliDate::to_fa(number_format((float)$r->position_1, 1)),
                    'imp' => PersianText::format_number((int)$r->impressions_1),
                ],
                'page_2' => [
                    'url' => $r->page_url_2, 'pid' => $p2 ?: 0,
                    'title' => $p2 ? (get_the_title($p2) ?: $r->page_url_2) : $r->page_url_2,
                    'pos' => JalaliDate::to_fa(number_format((float)$r->position_2, 1)),
                    'imp' => PersianText::format_number((int)$r->impressions_2),
                ],
                'detected' => JalaliDate::format($r->detected_at, 'relative'),
            ];
        }, $rows ?: []);
        wp_send_json_success(['rows' => $data]);
    }

    /* ------------------------------------------------------------------ */
    /* AI analysis of a specific conflict                                 */
    /* ------------------------------------------------------------------ */

    public function ajax_ai(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->ct()} WHERE id=%d", $id));
        if (!$row) wp_send_json_error('مورد یافت نشد.');

        $p1 = url_to_postid($row->page_url_1); $p2 = url_to_postid($row->page_url_2);
        $t1 = $p1 ? get_the_title($p1) : $row->page_url_1;
        $t2 = $p2 ? get_the_title($p2) : $row->page_url_2;
        $excerpt = function ($pid) {
            if (!$pid) return '';
            $c = get_post_field('post_content', $pid);
            $c = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(strip_shortcodes($c))));
            $words = preg_split('/\s+/u', $c, -1, PREG_SPLIT_NO_EMPTY);
            return implode(' ', array_slice($words, 0, 60)) . (count($words) > 60 ? '…' : '');
        };

        $system = 'شما متخصص ارشد سئوی فارسی هستید و در حل «هم‌نوع‌خواری کلمه کلیدی» (Cannibalization) تخصص دارید. '
                . 'بر اساس داده‌ها تصمیم دقیق و عملی بده. فقط فارسی و ساختارمند پاسخ بده.';
        $user = "کلمه کلیدی مورد تعارض: «{$row->keyword}»\n\n"
              . "صفحه ۱: {$t1}\nآدرس: {$row->page_url_1}\nجایگاه: {$row->position_1} | نمایش: {$row->impressions_1}\n"
              . ($p1 ? "خلاصه محتوا: " . $excerpt($p1) . "\n" : '')
              . "\nصفحه ۲: {$t2}\nآدرس: {$row->page_url_2}\nجایگاه: {$row->position_2} | نمایش: {$row->impressions_2}\n"
              . ($p2 ? "خلاصه محتوا: " . $excerpt($p2) . "\n" : '')
              . "\nتحلیل کن و بگو:\n"
              . "۱) کدام صفحه باید «صفحه‌ی برنده» باشد و چرا (بر اساس جایگاه، نمایش و کیفیت محتوا)\n"
              . "۲) بهترین راهکار چیست: ادغام کامل (ریدایرکت ۳۰۱)، کانونیکال، یا تفکیک محتوا (تغییر هدف کلمه‌ی یکی)؟ دلیلش را بگو\n"
              . "۳) اگر تفکیک پیشنهاد می‌دهی، برای صفحه‌ی بازنده چه کلمه‌ی هدف جایگزینی پیشنهاد می‌کنی؟\n"
              . "۴) اگر ادغام پیشنهاد می‌دهی، چه بخش‌هایی از صفحه‌ی بازنده باید به برنده منتقل شود؟\n"
              . "۵) یک جمع‌بندی یک‌خطی از اقدام نهایی.";

        $res = AiClient::chat($system, $user, 0.4);
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success(['text' => $res['text'], 'cost' => $res['cost'], 'tokens' => $res['tokens']]);
    }

    /* ------------------------------------------------------------------ */
    /* Auto-merge / consolidation                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Perform consolidation. POST: id, mode (canonical|redirect|merge), winner (1|2).
     * The non-winner becomes the "loser" that is canonicalized/redirected to the winner.
     */
    public function ajax_merge(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $mode = in_array($_POST['mode'] ?? '', ['canonical', 'redirect', 'merge'], true) ? $_POST['mode'] : 'canonical';
        $winnerSel = ((int)($_POST['winner'] ?? 1) === 2) ? 2 : 1;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->ct()} WHERE id=%d", $id));
        if (!$row) wp_send_json_error('مورد یافت نشد.');

        $winnerUrl = $winnerSel === 1 ? $row->page_url_1 : $row->page_url_2;
        $loserUrl  = $winnerSel === 1 ? $row->page_url_2 : $row->page_url_1;
        $loserPid  = url_to_postid($loserUrl);
        $winnerPid = url_to_postid($winnerUrl);

        if ($winnerUrl === $loserUrl) wp_send_json_error('دو صفحه یکسان هستند.');

        $messages = [];

        if ($mode === 'merge') {
            if (!$loserPid || !$winnerPid) wp_send_json_error('برای ادغام محتوا، هر دو صفحه باید پست وردپرسی باشند.');
            $loserContent = get_post_field('post_content', $loserPid);
            $loserTitle = get_the_title($loserPid);
            $winnerContent = get_post_field('post_content', $winnerPid);
            $merged = $winnerContent
                . "\n\n<!-- محتوای ادغام‌شده از: {$loserTitle} (ViraSEO) -->\n"
                . '<h2>' . esc_html($loserTitle) . "</h2>\n"
                . $loserContent;
            wp_update_post(['ID' => $winnerPid, 'post_content' => $merged]);
            $messages[] = 'محتوای صفحه‌ی بازنده به انتهای صفحه‌ی برنده اضافه شد.';
            // After merging, also 301 the loser to the winner
            $this->add_redirect($loserUrl, $winnerUrl);
            if ($loserPid) wp_update_post(['ID' => $loserPid, 'post_status' => 'draft']);
            $messages[] = 'صفحه‌ی بازنده پیش‌نویس شد و ۳۰۱ به برنده تنظیم شد.';
        } elseif ($mode === 'redirect') {
            $this->add_redirect($loserUrl, $winnerUrl);
            $messages[] = 'ریدایرکت ۳۰۱ از صفحه‌ی بازنده به برنده فعال شد.';
        } else { // canonical
            if ($loserPid) {
                update_post_meta($loserPid, 'rank_math_canonical_url', esc_url_raw($winnerUrl));
                $this->add_canonical($loserPid, $winnerUrl);
                $messages[] = 'کانونیکال صفحه‌ی بازنده به برنده اشاره داده شد (هم در Rank Math و هم به‌صورت مستقل).';
            } else {
                wp_send_json_error('برای تنظیم کانونیکال، صفحه‌ی بازنده باید پست وردپرسی باشد.');
            }
        }

        $wpdb->update($this->ct(), [
            'status' => 'resolved',
            'recommended_action' => $mode,
        ], ['id' => $id]);

        wp_send_json_success([
            'message' => '✅ ' . implode(' ', $messages),
            'winner' => $winnerUrl,
            'loser' => $loserUrl,
        ]);
    }

    public function ajax_resolve(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $status = in_array($_POST['status'] ?? 'ignored', ['detected', 'resolved', 'ignored'], true) ? $_POST['status'] : 'ignored';
        if ($id) $wpdb->update($this->ct(), ['status' => $status], ['id' => $id]);
        wp_send_json_success();
    }

    /* ------------------------------------------------------------------ */
    /* Redirect / canonical storage + serving                             */
    /* ------------------------------------------------------------------ */

    private function add_redirect(string $from, string $to): void {
        $map = get_option(self::REDIRECT_OPT, []);
        if (!is_array($map)) $map = [];
        $path = $this->normalize_path($from);
        if ($path) { $map[$path] = esc_url_raw($to); update_option(self::REDIRECT_OPT, $map, false); }
    }

    private function add_canonical(int $pid, string $to): void {
        $map = get_option(self::CANONICAL_OPT, []);
        if (!is_array($map)) $map = [];
        $map[$pid] = esc_url_raw($to);
        update_option(self::CANONICAL_OPT, $map, false);
    }

    /** Reduce a URL to a normalized path (with trailing slash) for matching. */
    private function normalize_path(string $url): string {
        $p = wp_parse_url($url, PHP_URL_PATH) ?: '/';
        $p = '/' . trim($p, '/');
        return $p === '/' ? '' : $p; // never redirect the homepage
    }

    /** Front-end: 301 any path in our merge-redirect map. */
    public function maybe_redirect(): void {
        if (is_admin()) return;
        $map = get_option(self::REDIRECT_OPT, []);
        if (!is_array($map) || !$map) return;
        $current = '/' . trim(wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
        if (isset($map[$current]) && $map[$current]) {
            wp_redirect($map[$current], 301);
            exit;
        }
    }

    /** Front-end: output a canonical tag for consolidated loser pages (fallback when Rank Math is absent). */
    public function maybe_canonical(): void {
        if (is_admin() || !is_singular()) return;
        // If Rank Math is active it already handles rank_math_canonical_url — avoid duplicate tags.
        if (defined('RANK_MATH_VERSION')) return;
        $map = get_option(self::CANONICAL_OPT, []);
        if (!is_array($map) || !$map) return;
        $pid = get_queried_object_id();
        if ($pid && !empty($map[$pid])) {
            echo '<link rel="canonical" href="' . esc_url($map[$pid]) . '" />' . "\n";
        }
    }

    /** Used by ActionPlan / dashboard. */
    public static function open_count(): int {
        global $wpdb;
        $t = $wpdb->prefix . 'viraseo_cannibalization';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) return 0;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status='detected'");
    }
}
