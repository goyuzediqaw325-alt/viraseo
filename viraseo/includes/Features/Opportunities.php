<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\{JalaliDate, PersianText};

/**
 * SEO Opportunities [🟢 مستقل]
 *  - Internal link opportunities: pages with high GSC impressions but few inbound internal links.
 *  - Thin content: pages with low word count (prioritized by GSC impressions) to rewrite.
 */
class Opportunities {
    public function __construct() {
        add_action('wp_ajax_viraseo_link_opportunities', [$this, 'ajax_link_opportunities']);
        add_action('wp_ajax_viraseo_thin_content', [$this, 'ajax_thin_content']);
        add_action('wp_ajax_viraseo_onpage', [$this, 'ajax_onpage']);
        add_action('wp_ajax_viraseo_onpage_fix', [$this, 'ajax_onpage_fix']);
    }

    /** Persian-aware "contains" check (normalized). */
    private function has(string $haystack, string $needle): bool {
        if ($needle === '') return false;
        return mb_stripos(PersianText::normalize($haystack), PersianText::normalize($needle)) !== false;
    }

    /**
     * On-Page SEO checklist for each page vs its target keyword (Persian-aware).
     * Checks title, H1, intro, URL, meta, density, subheadings, image alt, internal links.
     */
    public function ajax_onpage(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $types = $this->req_types();
        $in = "'" . implode("','", array_map('esc_sql', $types)) . "'";
        $posts = $wpdb->get_results("SELECT ID, post_title, post_content, post_name, post_type FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ({$in}) LIMIT 1500");
        $impr = $this->gsc_by_post();

        $rows = [];
        foreach ($posts as $p) {
            if (\ViraSEO\Features\TargetKeywords::is_excluded((int)$p->ID)) continue;
            $kw = \ViraSEO\Features\TargetKeywords::get((int)$p->ID);
            if ($kw === '') continue; // need a target keyword to audit

            $html = $p->post_content;
            $textRaw = wp_strip_all_tags($html);
            $text = PersianText::normalize(mb_strtolower($textRaw));
            $nkw = PersianText::normalize(mb_strtolower($kw));
            $words = max(1, PersianText::word_count($textRaw));

            $seoTitle = (string) get_post_meta($p->ID, 'rank_math_title', true);
            if ($seoTitle === '') $seoTitle = $p->post_title;
            $h1 = '';
            if (preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $m)) $h1 = wp_strip_all_tags($m[1]); else $h1 = $p->post_title;
            $intro = mb_substr($textRaw, 0, 400);
            $meta = (string) get_post_meta($p->ID, 'rank_math_description', true);
            $slug = urldecode($p->post_name);
            $occ = substr_count($text, $nkw);
            $density = round($occ / $words * 100, 2);

            $subhead = false;
            if (preg_match_all('/<h[2-3][^>]*>(.*?)<\/h[2-3]>/si', $html, $hm)) {
                foreach ($hm[1] as $h) if ($this->has($h, $kw)) { $subhead = true; break; }
            }
            $alt = false;
            if (preg_match_all('/<img[^>]*alt=["\']([^"\']*)["\'][^>]*>/i', $html, $im)) {
                foreach ($im[1] as $a) if ($this->has($a, $kw)) { $alt = true; break; }
            }
            $host = wp_parse_url(get_site_url(), PHP_URL_HOST);
            $intLinks = 0; $extLinks = 0;
            if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $html, $am)) {
                foreach ($am[1] as $href) {
                    if (strpos($href, '#') === 0 || stripos($href, 'mailto:') === 0) continue;
                    if (strpos($href, '/') === 0) { $intLinks++; continue; }
                    $h = wp_parse_url($href, PHP_URL_HOST);
                    if ($h && $h === $host) $intLinks++;
                    elseif (preg_match('#^https?://#i', $href)) $extLinks++;
                }
            }
            $h2count = preg_match_all('/<h2\b/i', $html) ?: 0;
            $imgCount = preg_match_all('/<img\b/i', $html) ?: 0;
            $hasSchema = (bool) preg_match('/application\/ld\+json/i', $html) || (bool) preg_match('/itemtype=/i', $html);
            $titleLen = mb_strlen($seoTitle);
            $metaLen = mb_strlen($meta);
            // Readability: average words per sentence
            $sentences = preg_split('/[.؟!\n]+/u', $textRaw, -1, PREG_SPLIT_NO_EMPTY);
            $avgSentence = $sentences ? $words / max(1, count($sentences)) : 0;

            $checks = [
                ['l'=>'کلمه هدف در عنوان سئو', 'ok'=>$this->has($seoTitle, $kw)],
                ['l'=>'کلمه هدف در H1', 'ok'=>$this->has($h1, $kw)],
                ['l'=>'کلمه هدف در ۱۰۰ کلمه‌ی ابتدایی', 'ok'=>$this->has($intro, $kw)],
                ['l'=>'کلمه هدف در URL (اسلاگ)', 'ok'=>$this->has($slug, $kw) || $this->has(str_replace('-', ' ', $slug), $kw)],
                ['l'=>'کلمه هدف در توضیحات متا', 'ok'=>$this->has($meta, $kw)],
                ['l'=>'کلمه هدف در یک زیرعنوان (H2/H3)', 'ok'=>$subhead],
                ['l'=>'چگالی کلمه مناسب (۰.۵٪ تا ۳٪)', 'ok'=>($density >= 0.5 && $density <= 3), 'note'=>'چگالی: '.JalaliDate::to_fa((string)$density).'٪'],
                ['l'=>'کلمه هدف در alt یک تصویر', 'ok'=>$alt],
                ['l'=>'حداقل ۳ لینک داخلی خروجی', 'ok'=>($intLinks >= 3), 'note'=>JalaliDate::to_fa((string)$intLinks).' لینک'],
                ['l'=>'حداقل ۱ لینک خارجی معتبر (Citation)', 'ok'=>($extLinks >= 1), 'note'=>JalaliDate::to_fa((string)$extLinks).' لینک'],
                ['l'=>'طول محتوای کافی (۳۰۰+ کلمه)', 'ok'=>($words >= 300), 'note'=>PersianText::format_number($words).' کلمه'],
                ['l'=>'ساختار با حداقل ۲ زیرعنوان H2', 'ok'=>($h2count >= 2), 'note'=>JalaliDate::to_fa((string)$h2count).' H2'],
                ['l'=>'حداقل ۱ تصویر در محتوا', 'ok'=>($imgCount >= 1)],
                ['l'=>'وجود داده ساختاریافته (Schema)', 'ok'=>$hasSchema],
                ['l'=>'طول عنوان سئو مناسب (۳۰ تا ۶۵ نویسه)', 'ok'=>($titleLen >= 30 && $titleLen <= 65), 'note'=>JalaliDate::to_fa((string)$titleLen)],
                ['l'=>'طول توضیحات متا مناسب (۱۲۰ تا ۱۶۰)', 'ok'=>($metaLen >= 120 && $metaLen <= 160), 'note'=>JalaliDate::to_fa((string)$metaLen)],
                ['l'=>'خوانایی خوب (میانگین جمله < ۳۰ کلمه)', 'ok'=>($avgSentence > 0 && $avgSentence < 30), 'note'=>'میانگین: '.JalaliDate::to_fa((string)round($avgSentence))],
            ];
            $passed = count(array_filter($checks, fn($c)=>$c['ok']));
            $score = (int) round($passed / count($checks) * 100);
            if ($score >= 90) continue;

            $rows[] = [
                'id'=>$p->ID, 'title'=>$p->post_title ?: '(بدون عنوان)', 'type'=>$this->type_label((int)$p->ID),
                'keyword'=>$kw, 'url'=>get_permalink($p->ID), 'edit'=>get_edit_post_link($p->ID,'raw'),
                'score'=>$score, 'impr_raw'=>$impr[$p->ID]['impr'] ?? 0,
                'impressions'=>PersianText::format_number($impr[$p->ID]['impr'] ?? 0),
                'checks'=>$checks,
            ];
        }
        usort($rows, fn($a, $b) => ($b['impr_raw'] <=> $a['impr_raw']) ?: ($a['score'] <=> $b['score']));
        wp_send_json_success(['rows'=>array_slice($rows, 0, 300), 'types'=>self::type_options()]);
    }

    /** Aggregate GSC impressions/clicks per page URL → keyed by post ID. */
    private function gsc_by_post(): array {
        global $wpdb;
        $gt = $wpdb->prefix . 'viraseo_gsc_keywords';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$gt}'") !== $gt) return [];
        $rows = $wpdb->get_results("SELECT page_url, SUM(impressions) impr, SUM(clicks) clk, AVG(position) pos FROM {$gt} GROUP BY page_url");
        $map = [];
        foreach ($rows as $r) {
            $pid = url_to_postid($r->page_url);
            if (!$pid) continue;
            if (!isset($map[$pid])) $map[$pid] = ['impr'=>0,'clk'=>0,'pos'=>0];
            $map[$pid]['impr'] += (int)$r->impr;
            $map[$pid]['clk']  += (int)$r->clk;
            $map[$pid]['pos']   = (float)$r->pos;
        }
        return $map;
    }

    /** Inbound internal-link counts per post ID (from the scanned internal_links table). */
    private function inlinks_by_post(): array {
        global $wpdb;
        $lt = $wpdb->prefix . 'viraseo_internal_links';
        $map = [];
        foreach ($wpdb->get_results("SELECT target_id, COUNT(*) c FROM {$lt} GROUP BY target_id") as $r) {
            $map[(int)$r->target_id] = (int)$r->c;
        }
        return $map;
    }

    private function req_types(): array {
        $pt = sanitize_text_field($_POST['post_type'] ?? '');
        $all = \ViraSEO\Features\TargetKeywords::public_types();
        return ($pt && $pt !== 'all' && in_array($pt, $all, true)) ? [$pt] : $all;
    }
    public static function type_options(): array {
        $out = [];
        foreach (\ViraSEO\Features\TargetKeywords::public_types() as $t) {
            $o = get_post_type_object($t);
            $out[] = ['slug'=>$t, 'label'=>$o ? $o->labels->name : $t];
        }
        return $out;
    }
    private function type_label(int $pid): string {
        $o = get_post_type_object(get_post_type($pid));
        return $o ? $o->labels->singular_name : get_post_type($pid);
    }

    public function ajax_link_opportunities(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $gsc = $this->gsc_by_post();
        if (!$gsc) wp_send_json_error('ابتدا داده‌های سرچ کنسول را همگام‌سازی و سپس «اسکن لینک‌ها» را اجرا کنید.');
        $inlinks = $this->inlinks_by_post();
        $types = $this->req_types();

        $rows = [];
        foreach ($gsc as $pid => $g) {
            if (\ViraSEO\Features\TargetKeywords::is_excluded((int)$pid)) continue;
            if (!in_array(get_post_type($pid), $types, true)) continue;
            $in = $inlinks[$pid] ?? 0;
            // Opportunity = decent impressions but ≤ 3 internal inbound links
            if ($g['impr'] < 50 || $in > 3) continue;
            $rows[] = [
                'id'=>$pid,
                'title'=>get_the_title($pid) ?: '(بدون عنوان)',
                'type'=>$this->type_label((int)$pid),
                'url'=>get_permalink($pid),
                'edit'=>get_edit_post_link($pid, 'raw'),
                'impr_raw'=>$g['impr'],
                'impressions'=>PersianText::format_number($g['impr']),
                'clicks'=>PersianText::format_number($g['clk']),
                'position'=>JalaliDate::to_fa(number_format($g['pos'], 1)),
                'inlinks'=>JalaliDate::to_fa($in),
                'inlinks_raw'=>$in,
            ];
        }
        // Highest impressions + fewest links first (biggest opportunity)
        usort($rows, fn($a, $b) => ($b['impr_raw'] <=> $a['impr_raw']));
        wp_send_json_success(['rows'=>array_slice($rows, 0, 300), 'types'=>self::type_options()]);
    }

    public function ajax_thin_content(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;

        $threshold = max(50, absint($_POST['threshold'] ?? 300));
        $gsc = $this->gsc_by_post();
        $types = $this->req_types();
        $in = "'" . implode("','", array_map('esc_sql', $types)) . "'";
        $posts = $wpdb->get_results("SELECT ID, post_title, post_content, post_type FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ({$in}) LIMIT 2000");

        $rows = [];
        foreach ($posts as $p) {
            if (\ViraSEO\Features\TargetKeywords::is_excluded((int)$p->ID)) continue;
            $wc = PersianText::word_count(wp_strip_all_tags($p->post_content));
            if ($wc >= $threshold) continue;
            $impr = $gsc[$p->ID]['impr'] ?? 0;
            $rows[] = [
                'id'=>$p->ID,
                'title'=>$p->post_title ?: '(بدون عنوان)',
                'type'=>$this->type_label((int)$p->ID),
                'url'=>get_permalink($p->ID),
                'edit'=>get_edit_post_link($p->ID, 'raw'),
                'words'=>$wc,
                'words_fa'=>PersianText::format_number($wc),
                'impr_raw'=>$impr,
                'impressions'=>PersianText::format_number($impr),
                'priority'=> $impr > 200 ? 'بالا' : ($impr > 0 ? 'متوسط' : 'پایین'),
            ];
        }
        // Pages that already get impressions but are thin = highest rewrite ROI
        usort($rows, fn($a, $b) => ($b['impr_raw'] <=> $a['impr_raw']) ?: ($a['words'] <=> $b['words']));
        wp_send_json_success(['rows'=>array_slice($rows, 0, 300), 'threshold'=>$threshold, 'types'=>self::type_options()]);
    }

    /** AI auto-fix for on-page SEO issues. Reads problems list, rewrites content to fix them. */
    public function ajax_onpage_fix(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!\ViraSEO\Api\AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');
        $pid = absint($_POST['post_id'] ?? 0);
        $post = $pid ? get_post($pid) : null;
        if (!$post) wp_send_json_error('صفحه یافت نشد.');

        $issues = isset($_POST['issues']) ? array_map('sanitize_text_field', (array)$_POST['issues']) : [];
        $title = get_the_title($pid);
        $content = $post->post_content;
        $target = \ViraSEO\Features\TargetKeywords::get($pid);
        $words = preg_split('/\s+/u', wp_strip_all_tags(strip_shortcodes($content)));
        $contentPreview = implode(' ', array_slice($words, 0, 800));

        $issueList = implode("\n", array_map(fn($i) => "- {$i}", $issues));
        $system = 'شما متخصص سئوی on-page فارسی هستید. وظیفه: محتوای موجود را طوری ویرایش/تکمیل کن که ایرادهای سئوی مشخص‌شده رفع شوند. '
                . 'بخش‌های سالم را دست‌نخورده نگه دار. ایرادها را دقیقاً رفع کن (مثلاً لینک خارجی اضافه کن، H2 دوم بنویس، تصویر placeholder بذار). '
                . 'خروجی باید HTML فارسی کامل باشد. مهم: فقط محتوای نهایی HTML. هیچ توضیح/یادداشت/code fence نده.';
        $user = "عنوان: {$title}\n" . ($target ? "کلمه هدف: «{$target}»\n" : '')
              . "\nایرادهای on-page که باید رفع شوند:\n{$issueList}\n\n"
              . "محتوای فعلی:\n{$contentPreview}\n\n"
              . "محتوای اصلاح‌شده (HTML) را برگردان.";

        $res = \ViraSEO\Api\AiClient::chat($system, $user, 0.4, 8000);
        if (isset($res['error'])) wp_send_json_error($res['error']);

        // Clean AI response
        $text = $res['text'];
        $text = preg_replace('/^```(?:html)?\s*\n?/im', '', $text);
        $text = preg_replace('/\n?```\s*$/im', '', $text);
        if (preg_match('/^(.*?)(<(?:h[1-6]|p|div|ul|ol|table|section|article|blockquote)[>\s])/uis', $text, $m) && strlen(trim($m[1])) > 0) {
            $text = substr($text, strlen($m[1]));
        }
        $text = preg_replace('/(<\/(?:p|div|ul|ol|table|section|article|blockquote|h[1-6])>)\s*[^<]+$/uis', '$1', $text);
        $text = trim($text);

        update_post_meta($pid, '_viraseo_proposed_content', $text);
        wp_send_json_success([
            'post_id' => $pid, 'title' => $title,
            'old_content' => $content, 'new_content' => $text,
            'cost' => $res['cost'], 'tokens' => $res['tokens'],
        ]);
    }
}
