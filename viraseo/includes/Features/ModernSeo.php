<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\{JalaliDate, PersianText};

/**
 * Modern SEO 2026 [🟢 مستقل] — Persian-focused, trend-aligned:
 *  - AI/GEO readiness (optimize to be cited by AI Overviews / answer engines).
 *  - Content freshness/decay (stale ranking pages to refresh).
 *  - Persian text quality (ZWNJ/نیم‌فاصله, Arabic chars, readability).
 *  - llms.txt generator (served live at /llms.txt) to guide AI crawlers.
 */
class ModernSeo {
    const TYPES = ['post','page','product'];

    public function __construct() {
        add_action('wp_ajax_viraseo_ai_readiness', [$this, 'ajax_ai_readiness']);
        add_action('wp_ajax_viraseo_freshness', [$this, 'ajax_freshness']);
        add_action('wp_ajax_viraseo_persian_quality', [$this, 'ajax_persian_quality']);
        add_action('wp_ajax_viraseo_persian_fix', [$this, 'ajax_persian_fix']);
        add_action('wp_ajax_viraseo_llms_txt', [$this, 'ajax_llms_txt']);
        // Serve a live llms.txt at the site root (rewrite rule + early + template_redirect fallbacks)
        add_action('init', [$this, 'add_rewrite']);
        add_filter('query_vars', function($v){ $v[] = 'viraseo_llms'; return $v; });
        add_action('template_redirect', [$this, 'serve_llms_txt']);
        add_action('init', [$this, 'serve_llms_txt'], 99);
    }

    public function add_rewrite(): void {
        add_rewrite_rule('^llms\.txt$', 'index.php?viraseo_llms=1', 'top');
    }

    private function posts(int $limit = 400): array {
        global $wpdb;
        $types = $this->req_types();
        $in = "'" . implode("','", array_map('esc_sql', $types)) . "'";
        $rows = $wpdb->get_results("SELECT ID,post_title,post_content,post_modified,post_type FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ({$in}) LIMIT {$limit}");
        return array_values(array_filter($rows, fn($p) => !TargetKeywords::is_excluded((int)$p->ID)));
    }

    /** Post types from the request filter, defaulting to all public types. */
    private function req_types(): array {
        $pt = sanitize_text_field($_POST['post_type'] ?? '');
        $all = TargetKeywords::public_types();
        return ($pt && $pt !== 'all' && in_array($pt, $all, true)) ? [$pt] : $all;
    }

    /** Public post types for the filter dropdown. */
    public static function type_options(): array {
        $out = [];
        foreach (TargetKeywords::public_types() as $t) {
            $o = get_post_type_object($t);
            $out[] = ['slug'=>$t, 'label'=>$o ? $o->labels->name : $t];
        }
        return $out;
    }

    private function type_label(int $pid): string {
        $o = get_post_type_object(get_post_type($pid));
        return $o ? $o->labels->singular_name : get_post_type($pid);
    }

    private function gsc_impr_map(): array {
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_gsc_keywords';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) return [];
        $map = [];
        foreach ($wpdb->get_results("SELECT page_url, SUM(impressions) i FROM {$t} GROUP BY page_url") as $r) {
            $pid = url_to_postid($r->page_url);
            if ($pid) $map[$pid] = (int)$r->i;
        }
        return $map;
    }

    /** AI / Generative Engine Optimization readiness — score each page for answer-engine citation. */
    public function ajax_ai_readiness(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $rows = [];
        foreach ($this->posts() as $p) {
            $html = $p->post_content;
            $text = wp_strip_all_tags($html);
            $wc = PersianText::word_count($text);
            $score = 0; $tips = [];

            // Concise lead answer (first ~50 words present)
            if ($wc >= 40) $score += 15; else $tips[] = 'یک پاراگراف ابتدایی کوتاه که مستقیم به سوال پاسخ دهد اضافه کنید';
            // Question headings (great for AI extraction)
            $hasQ = preg_match('/<h[2-3][^>]*>[^<]*(چگونه|چطور|چیست|چرا|آیا|کدام|چند)[^<]*<\/h[2-3]>/u', $html)
                  || preg_match('/<h[2-3][^>]*>[^<]*؟\s*<\/h[2-3]>/u', $html);
            if ($hasQ) $score += 25; else $tips[] = 'زیرعنوان‌های پرسشی (H2/H3) با «چگونه/چیست/چرا...» اضافه کنید';
            // Lists (extractable)
            if (preg_match('/<(ul|ol)[^>]*>/i', $html)) $score += 15; else $tips[] = 'از فهرست (لیست) برای مراحل/نکات استفاده کنید';
            // FAQ section
            if (preg_match('/سوالات متداول|پرسش‌های متداول|FAQ/iu', $html)) $score += 15; else $tips[] = 'بخش «سوالات متداول» با پاسخ‌های کوتاه اضافه کنید (برای AI Overview)';
            // Depth
            if ($wc >= 600) $score += 15; elseif ($wc >= 300) $score += 8; else $tips[] = 'محتوا را عمیق‌تر کنید (حداقل ۶۰۰ کلمه)';
            // Media / tables
            if (preg_match('/<table|<img/i', $html)) $score += 10; else $tips[] = 'جدول یا تصویر توضیحی اضافه کنید';

            if ($score >= 75) continue; // already good — show only pages needing work
            $rows[] = [
                'id'=>$p->ID, 'title'=>$p->post_title ?: '(بدون عنوان)', 'type'=>$this->type_label($p->ID),
                'url'=>get_permalink($p->ID), 'edit'=>get_edit_post_link($p->ID,'raw'),
                'score'=>$score, 'tips'=>array_slice($tips, 0, 4),
            ];
        }
        usort($rows, fn($a,$b)=>$a['score']<=>$b['score']);
        wp_send_json_success(['rows'=>array_slice($rows, 0, 300), 'types'=>self::type_options()]);
    }

    /** Content freshness — stale pages that still rank (have impressions) → refresh ROI. */
    public function ajax_freshness(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $months = max(1, absint($_POST['months'] ?? 6));
        $cutoff = strtotime("-{$months} months");
        $impr = $this->gsc_impr_map();
        $rows = [];
        foreach ($this->posts() as $p) {
            $mod = strtotime($p->post_modified);
            if ($mod > $cutoff) continue;
            $i = $impr[$p->ID] ?? 0;
            $age = (int) floor((time() - $mod) / 2629800); // months
            $rows[] = [
                'id'=>$p->ID, 'title'=>$p->post_title ?: '(بدون عنوان)', 'type'=>$this->type_label($p->ID),
                'url'=>get_permalink($p->ID), 'edit'=>get_edit_post_link($p->ID,'raw'),
                'modified'=>JalaliDate::format($p->post_modified, 'long'),
                'age'=>JalaliDate::to_fa((string)$age),
                'impr_raw'=>$i, 'impressions'=>PersianText::format_number($i),
                'priority'=> $i > 200 ? 'بالا' : ($i > 0 ? 'متوسط' : 'پایین'),
            ];
        }
        usort($rows, fn($a,$b)=>$b['impr_raw']<=>$a['impr_raw']);
        wp_send_json_success(['rows'=>array_slice($rows, 0, 300), 'months'=>$months, 'types'=>self::type_options()]);
    }

    /** Persian text quality — ZWNJ (نیم‌فاصله), Arabic characters, readability. */
    public function ajax_persian_quality(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $rows = [];
        foreach ($this->posts() as $p) {
            $text = wp_strip_all_tags($p->post_content);
            if ($text === '') continue;
            $issues = [];

            // Arabic ي / ك instead of Persian ی / ک
            $arabic = preg_match_all('/[\x{064A}\x{0643}]/u', $text);
            if ($arabic > 0) $issues[] = ['type'=>'حروف عربی', 'count'=>$arabic, 'hint'=>'ي/ك عربی را به ی/ک فارسی تبدیل کنید'];

            // Missing ZWNJ: standalone «می»/«نمی» before a verb (should be می‌/نمی‌)
            $mi = preg_match_all('/(?:^|\s)ن?می [\x{0600}-\x{06FF}]/u', $text);
            if ($mi > 2) $issues[] = ['type'=>'نیم‌فاصله «می»', 'count'=>$mi, 'hint'=>'«می» و «نمی» را با نیم‌فاصله به فعل بچسبانید (می‌رود)'];

            // Standalone plural «ها» (should attach with ZWNJ)
            $ha = preg_match_all('/[\x{0600}-\x{06FF}] ها(?:\s|،|\.|$)/u', $text);
            if ($ha > 2) $issues[] = ['type'=>'نیم‌فاصله «ها»', 'count'=>$ha, 'hint'=>'پسوند جمع «ها» را با نیم‌فاصله بنویسید (خانه‌ها)'];

            // Readability: average sentence length
            $sentences = preg_split('/[.؟!]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
            $wc = PersianText::word_count($text);
            $avg = $sentences ? $wc / count($sentences) : 0;
            if ($avg > 30) $issues[] = ['type'=>'جملات بلند', 'count'=>(int)round($avg), 'hint'=>'میانگین طول جمله بالاست؛ جملات را کوتاه‌تر کنید'];

            if (!$issues) continue;
            $rows[] = [
                'id'=>$p->ID, 'title'=>$p->post_title ?: '(بدون عنوان)', 'type'=>$this->type_label($p->ID),
                'edit'=>get_edit_post_link($p->ID,'raw'), 'url'=>get_permalink($p->ID),
                'issues'=>array_map(fn($x)=>$x['type'].' ('.JalaliDate::to_fa((string)$x['count']).')'.' — '.$x['hint'], $issues),
                'count'=>count($issues),
            ];
        }
        usort($rows, fn($a,$b)=>$b['count']<=>$a['count']);
        wp_send_json_success(['rows'=>array_slice($rows, 0, 300), 'types'=>self::type_options()]);
    }

    /** Auto-fix Persian writing issues (Arabic chars + ZWNJ for می/نمی/ها) in a post's content. */
    public function ajax_persian_fix(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('دسترسی غیرمجاز.');
        $pid = absint($_POST['post_id'] ?? 0);
        $post = $pid ? get_post($pid) : null;
        if (!$post) wp_send_json_error('صفحه یافت نشد.');

        $z = "\xE2\x80\x8C"; // ZWNJ
        $tokens = preg_split('/(<[^>]+>)/u', $post->post_content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $changes = 0;
        foreach ($tokens as &$t) {
            if ($t === '' || $t[0] === '<') continue; // skip HTML tags
            $orig = $t;
            // Arabic ي/ك → Persian ی/ک
            $t = str_replace(['ي', 'ك'], ['ی', 'ک'], $t);
            // می / نمی + verb → attach with ZWNJ
            $t = preg_replace('/(^|[\s\x{200C}])(ن?می) ([\x{0600}-\x{06FF}])/u', '$1$2' . $z . '$3', $t);
            // plural «ها» (+ common suffixes) → attach with ZWNJ
            $t = preg_replace('/([\x{0600}-\x{06FF}]) (ها(?:ی|یی|یم|یت|یش|یمان|یتان|یشان)?)(?![\x{0600}-\x{06FF}])/u', '$1' . $z . '$2', $t);
            if ($t !== $orig) $changes++;
        }
        unset($t);
        $fixed = implode('', $tokens);
        if ($fixed === $post->post_content) wp_send_json_success(['message' => 'موردی برای اصلاح یافت نشد.']);
        wp_update_post(['ID' => $pid, 'post_content' => $fixed]);
        wp_send_json_success(['message' => '✅ متن فارسی اصلاح و ذخیره شد (' . PersianText::format_number($changes) . ' بخش).']);
    }

    /** Build llms.txt content (markdown) listing key pages to guide AI crawlers. */
    private function build_llms_txt(): string {
        $name = get_bloginfo('name');
        $desc = get_bloginfo('description');
        $out = "# {$name}\n\n";
        if ($desc) $out .= "> {$desc}\n\n";
        $out .= "این فایل صفحات کلیدی سایت را برای موتورهای هوش مصنوعی معرفی می‌کند.\n\n## صفحات مهم\n\n";

        $scores = get_option('viraseo_link_scores', []);
        if (is_array($scores) && $scores) {
            arsort($scores);
            $ids = array_slice(array_keys($scores), 0, 50);
        } else {
            $ids = get_posts(['post_type'=>self::TYPES, 'post_status'=>'publish', 'numberposts'=>50, 'fields'=>'ids', 'orderby'=>'comment_count', 'order'=>'DESC']);
        }
        foreach ($ids as $id) {
            $title = get_the_title($id);
            $url = get_permalink($id);
            if (!$title || !$url) continue;
            $excerpt = wp_strip_all_tags(get_the_excerpt($id) ?: '');
            $excerpt = mb_substr(trim($excerpt), 0, 120);
            $out .= "- [{$title}]({$url})" . ($excerpt ? ": {$excerpt}" : '') . "\n";
        }
        return $out;
    }

    public function ajax_llms_txt(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        wp_send_json_success(['content'=>$this->build_llms_txt(), 'url'=>home_url('/llms.txt')]);
    }

    /** Serve the generated llms.txt live at /llms.txt (no file write needed). */
    public function serve_llms_txt(): void {
        if (is_admin()) return;
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $match = (trim($uri, '/') === 'llms.txt') || (function_exists('get_query_var') && get_query_var('viraseo_llms'));
        if (!$match) return;
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo $this->build_llms_txt();
        exit;
    }
}

