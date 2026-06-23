<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\PersianText;

/**
 * Action Plan [🟢 مستقل] — the "do 90% of the work" guidance layer.
 * Aggregates the highest-impact SEO tasks across all features into one prioritized,
 * actionable checklist on the dashboard, each with a direct link to fix it.
 */
class ActionPlan {
    public function __construct() {
        add_action('wp_ajax_viraseo_action_plan', [$this, 'ajax_plan']);
    }

    private function admin(string $page): string { return admin_url('admin.php?page=' . $page); }

    public function ajax_plan(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $gt = $wpdb->prefix . 'viraseo_gsc_keywords';
        $has_gsc_table = ($wpdb->get_var("SHOW TABLES LIKE '{$gt}'") === $gt);
        $tasks = [];

        // 0) Setup checks
        $connected = (bool) get_option('viraseo_gsc_token');
        $kw_count = $has_gsc_table ? (int)$wpdb->get_var("SELECT COUNT(*) FROM {$gt}") : 0;
        $has_serper = !empty(\ViraSEO\Admin\Dashboard::get('serper_api_key'));
        $link_scores = get_option('viraseo_link_scores', []);
        $scanned = is_array($link_scores) && $link_scores;

        if (!$connected) {
            $tasks[] = $this->task(100, '🔌', 'اتصال به سرچ کنسول', 'برای تحلیل داده‌محور، ابتدا گوگل سرچ کنسول را متصل کنید. پایه‌ی همه‌ی تحلیل‌ها همین داده‌هاست.', 0, 'critical', 'اتصال', $this->admin('viraseo-gsc'));
        } elseif ($kw_count === 0) {
            $tasks[] = $this->task(98, '📥', 'دریافت داده‌های سرچ کنسول', 'اتصال برقرار است ولی داده‌ای همگام نشده. دکمه «دریافت داده‌ها» را بزنید.', 0, 'critical', 'دریافت', $this->admin('viraseo-gsc'));
        }
        if (!$has_serper) {
            $tasks[] = $this->task(70, '🔑', 'افزودن کلید Serper API', 'برای تحلیل رقبا و مانیتورینگ رتبه، کلید رایگان Serper را در تنظیمات وارد کنید.', 0, 'warn', 'تنظیمات', $this->admin('viraseo-settings'));
        }
        if (!$scanned) {
            $tasks[] = $this->task(80, '🔍', 'اجرای اولین اسکن لینک‌ها', 'برای محاسبه قدرت لینک، صفحات یتیم و پیشنهادهای لینک‌سازی، یک‌بار «اسکن لینک‌ها» را اجرا کنید.', 0, 'warn', 'اسکن لینک‌ها', $this->admin('viraseo-links'));
        }

        if ($has_gsc_table && $kw_count > 0) {
            // 1) Striking distance (position 11-20) = quickest wins
            $striking = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$gt} WHERE position > 10 AND position <= 20 AND impressions >= 30");
            if ($striking > 0) $tasks[] = $this->task(95, '🚀', PersianText::format_number($striking).' کلمه در آستانه‌ی صفحه اول', 'این کلمات جایگاه ۱۱ تا ۲۰ دارند؛ با کمی بهبود به صفحه اول می‌رسند. سریع‌ترین رشد ترافیک.', $striking, 'high', 'مشاهده فرصت‌ها', $this->admin('viraseo-forecast'));

            // 2) High-impression, low-CTR (good rank, weak title)
            $lowctr = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$gt} WHERE position <= 10 AND impressions >= 100 AND ctr < 0.02");
            if ($lowctr > 0) $tasks[] = $this->task(88, '🎯', PersianText::format_number($lowctr).' صفحه با کلیک کمتر از انتظار', 'رتبه خوب ولی نرخ کلیک پایین — عنوان و متای جذاب‌تر = کلیک بیشتر بدون تغییر رتبه.', $lowctr, 'high', 'تحلیل هوشمند', $this->admin('viraseo-gsc'));

            // 3) Zero-click high-impression
            $zero = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$gt} WHERE clicks = 0 AND impressions >= 100");
            if ($zero > 0) $tasks[] = $this->task(75, '👀', PersianText::format_number($zero).' کلمه پرنمایش بدون کلیک', 'نمایش زیاد ولی صفر کلیک — نیازمند بازنگری عنوان/محتوا.', $zero, 'warn', 'تحلیل هوشمند', $this->admin('viraseo-gsc'));
        }

        // 4) Cannibalization
        $cannibal = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}viraseo_cannibalization WHERE status='detected'");
        if ($cannibal > 0) $tasks[] = $this->task(85, '⚠️', PersianText::format_number($cannibal).' تعارض کلمه‌ای (Cannibalization)', 'چند صفحه روی یک کلمه رقابت می‌کنند و همدیگر را تضعیف می‌کنند. یکی‌سازی کنید.', $cannibal, 'high', 'بررسی', $this->admin('viraseo-gsc'));

        // 5) Orphan pages
        $orphan = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}viraseo_orphan_pages WHERE status='orphan'");
        if ($orphan > 0) $tasks[] = $this->task(78, '🔗', PersianText::format_number($orphan).' صفحه یتیم (بدون لینک داخلی)', 'این صفحات لینک داخلی ندارند و گوگل اهمیت‌شان را درک نمی‌کند. لینک داخلی بدهید.', $orphan, 'warn', 'لینک‌سازی', $this->admin('viraseo-links'));

        // 6) Pending link suggestions
        $sugg = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}viraseo_link_suggestions WHERE status='pending'");
        if ($sugg > 0) $tasks[] = $this->task(72, '🧩', PersianText::format_number($sugg).' پیشنهاد لینک داخلی آماده', 'پیشنهادهای لینک‌سازی هوشمند آماده‌ی درج هستند. با «درج خودکار» اعمال کنید.', $sugg, 'normal', 'پیشنهادها', $this->admin('viraseo-links'));

        // 7) Pages missing a target keyword
        $missing = $this->count_missing_targets();
        if ($missing > 0) $tasks[] = $this->task(68, '🏷️', PersianText::format_number($missing).' صفحه بدون کلمه هدف', 'تعیین کلمه هدف، لینک‌سازی و خوشه‌بندی را هوشمند می‌کند. می‌توانید خودکار از سرچ کنسول بگیرید.', $missing, 'normal', 'کلمات هدف', $this->admin('viraseo-targets'));

        // Sort by priority desc
        usort($tasks, fn($a, $b) => $b['priority'] <=> $a['priority']);

        // A friendly health score: fewer/lighter open tasks = higher
        $score = 100;
        foreach ($tasks as $t) $score -= ($t['severity'] === 'critical' ? 20 : ($t['severity'] === 'high' ? 10 : ($t['severity'] === 'warn' ? 5 : 2)));
        $score = max(5, min(100, $score));

        wp_send_json_success(['tasks'=>array_values($tasks), 'score'=>$score, 'done'=>empty($tasks)]);
    }

    private function task(int $priority, string $icon, string $title, string $desc, int $count, string $severity, string $btn, string $url): array {
        return compact('priority','icon','title','desc','count','severity','btn','url');
    }

    /** Count published public posts that have neither a ViraSEO nor Rank Math target keyword. */
    private function count_missing_targets(): int {
        global $wpdb;
        $types = TargetKeywords::public_types();
        $in = implode(',', array_fill(0, count($types), '%s'));
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             WHERE p.post_status='publish' AND p.post_type IN ($in)
             AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='_viraseo_target_keyword' AND m.meta_value<>'')
             AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m2 WHERE m2.post_id=p.ID AND m2.meta_key='rank_math_focus_keyword' AND m2.meta_value<>'')",
            ...$types
        );
        return (int) $wpdb->get_var($sql);
    }
}
