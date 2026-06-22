<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Api\WebhookHandler;
use ViraSEO\Utils\{JalaliDate, PersianText};

/**
 * Feature: Keyword Rank Monitoring [🟢 مستقل — از Serper استفاده می‌کند]
 * Tracks Google ranking of user-defined keywords for THIS site, on a schedule
 * (daily / every 2 days / weekly) via Action Scheduler. Stores rank history.
 */
class RankMonitor {
    const HOOK = 'viraseo_rank_update';

    public function __construct() {
        add_action('init', [$this, 'ensure_schedule']);
        add_action(self::HOOK, [$this, 'run_due']);
        add_action('wp_ajax_viraseo_rank_add', [$this, 'ajax_add']);
        add_action('wp_ajax_viraseo_rank_remove', [$this, 'ajax_remove']);
        add_action('wp_ajax_viraseo_rank_list', [$this, 'ajax_list']);
        add_action('wp_ajax_viraseo_rank_check', [$this, 'ajax_check']);
    }

    /** Register a recurring background action that runs every 12h (decides due items inside). */
    public function ensure_schedule(): void {
        if (!function_exists('as_schedule_recurring_action') || !function_exists('as_next_scheduled_action')) return;
        if (as_next_scheduled_action(self::HOOK) === false) {
            as_schedule_recurring_action(time() + 300, 12 * HOUR_IN_SECONDS, self::HOOK, [], 'viraseo');
        }
    }

    public function ajax_add(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $kw = PersianText::normalize(sanitize_text_field($_POST['keyword'] ?? ''));
        if (!$kw) wp_send_json_error('کلمه کلیدی وارد کنید.');
        $freq = in_array($_POST['frequency'] ?? '', ['daily','2days','weekly'], true) ? $_POST['frequency'] : 'daily';
        $target = esc_url_raw($_POST['target_url'] ?? '');

        $t = $wpdb->prefix . 'viraseo_rank_tracking';
        $ok = $wpdb->insert($t, [
            'keyword'=>$kw, 'keyword_hash'=>md5(mb_strtolower($kw)),
            'target_url'=>$target ?: null, 'frequency'=>$freq, 'status'=>'active',
        ]);
        if ($ok === false) wp_send_json_error('این کلمه قبلاً اضافه شده است.');
        $id = $wpdb->insert_id;
        // Check immediately so the user sees a rank right away
        $this->check_keyword($id);
        wp_send_json_success(['message'=>'✅ کلمه اضافه و بررسی شد.']);
    }

    public function ajax_remove(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        if ($id) $wpdb->delete($wpdb->prefix.'viraseo_rank_tracking', ['id'=>$id]);
        wp_send_json_success();
    }

    public function ajax_check(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        if ($id) {
            $res = $this->check_keyword($id);
            if (isset($res['error'])) wp_send_json_error($res['error']);
        } else {
            // check all active
            $ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}viraseo_rank_tracking WHERE status='active'");
            foreach ($ids as $kid) $this->check_keyword((int)$kid);
        }
        wp_send_json_success(['message'=>'✅ رتبه‌ها به‌روزرسانی شد.']);
    }

    public function ajax_list(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_rank_tracking ORDER BY (current_rank IS NULL), current_rank ASC, id DESC");
        $freq_fa = ['daily'=>'روزانه','2days'=>'هر ۲ روز','weekly'=>'هفتگی'];
        $data = array_map(function($r) use ($freq_fa) {
            $cur = $r->current_rank; $prev = $r->previous_rank;
            $change = 0;
            if ($cur !== null && $prev !== null) $change = (int)$prev - (int)$cur; // +improve / -drop
            return [
                'id'=>(int)$r->id,
                'keyword'=>$r->keyword,
                'current'=> $cur === null ? '۵۰+' : JalaliDate::to_fa($cur),
                'current_raw'=> $cur,
                'best'=> $r->best_rank === null ? '—' : JalaliDate::to_fa($r->best_rank),
                'change'=> $change,
                'found_url'=>$r->found_url,
                'freq'=>$freq_fa[$r->frequency] ?? $r->frequency,
                'last'=> $r->last_checked ? JalaliDate::format($r->last_checked, 'relative') : 'هنوز بررسی نشده',
                'history'=> array_values(array_slice((array)json_decode($r->history ?: '[]', true), -14)),
            ];
        }, $rows);
        wp_send_json_success(['rows'=>$data]);
    }

    /** Cron handler: check only keywords whose frequency interval has elapsed. */
    public function run_due(): void {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT id, frequency, last_checked FROM {$wpdb->prefix}viraseo_rank_tracking WHERE status='active'");
        $now = time();
        $intervals = ['daily'=>23*HOUR_IN_SECONDS, '2days'=>47*HOUR_IN_SECONDS, 'weekly'=>167*HOUR_IN_SECONDS];
        foreach ($rows as $r) {
            $due = !$r->last_checked || ($now - strtotime($r->last_checked)) >= ($intervals[$r->frequency] ?? DAY_IN_SECONDS);
            if ($due) {
                $this->check_keyword((int)$r->id);
                // small gap to be gentle on the Serper quota
                usleep(300000);
            }
        }
    }

    /** Query Serper for a keyword and locate this site's best position. */
    public function check_keyword(int $id): array {
        global $wpdb;
        $t = $wpdb->prefix . 'viraseo_rank_tracking';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id));
        if (!$row) return ['error'=>'یافت نشد.'];

        $res = WebhookHandler::serper_search($row->keyword, 50);
        if (isset($res['error'])) return $res;

        $site_host = wp_parse_url(get_site_url(), PHP_URL_HOST);
        $site_host = preg_replace('/^www\./', '', (string)$site_host);

        $rank = null; $found_url = null;
        foreach ($res['organic'] as $i => $item) {
            $link = $item['link'] ?? '';
            $h = preg_replace('/^www\./', '', (string) wp_parse_url($link, PHP_URL_HOST));
            if ($h && $h === $site_host) {
                $rank = (int)($item['position'] ?? ($i + 1));
                $found_url = $link;
                break;
            }
        }

        // Build history (keep last 60 points)
        $history = (array) json_decode($row->history ?: '[]', true);
        $history[] = ['d'=>JalaliDate::now()['date'], 'r'=>$rank];
        if (count($history) > 60) $history = array_slice($history, -60);

        $best = $row->best_rank;
        if ($rank !== null && ($best === null || $rank < (int)$best)) $best = $rank;

        $wpdb->update($t, [
            'previous_rank'=>$row->current_rank,
            'current_rank'=>$rank,
            'best_rank'=>$best,
            'found_url'=>$found_url,
            'history'=>wp_json_encode($history),
            'last_checked'=>current_time('mysql'),
        ], ['id'=>$id]);

        return ['rank'=>$rank];
    }
}
