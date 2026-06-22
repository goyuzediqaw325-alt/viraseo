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
        add_action('wp_ajax_viraseo_rank_pages', [$this, 'ajax_pages']);
        add_action('wp_ajax_viraseo_rank_alerts', [$this, 'ajax_alerts']);
    }

    /** Return recent rank-drop alerts for the panel. */
    public function ajax_alerts(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT detail, created_at FROM {$wpdb->prefix}viraseo_activity_log WHERE action='rank_drop' ORDER BY id DESC LIMIT 20");
        $data = array_map(function($r) {
            $d = json_decode($r->detail, true) ?: [];
            return [
                'keyword'=>$d['keyword'] ?? '',
                'from'=>$d['from'] ?? '—',
                'to'=>$d['to'] ?? '—',
                'time'=>JalaliDate::format($r->created_at, 'relative'),
            ];
        }, $rows ?: []);
        wp_send_json_success(['rows'=>$data]);
    }

    /** Update the per-keyword page count. */
    public function ajax_pages(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $pages = max(1, min(10, absint($_POST['max_pages'] ?? 3)));
        if ($id) $wpdb->update($wpdb->prefix.'viraseo_rank_tracking', ['max_pages'=>$pages], ['id'=>$id]);
        wp_send_json_success(['message'=>'تعداد صفحات این کلمه به '.$pages.' تغییر یافت.']);
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
        $pages = (int) ($_POST['max_pages'] ?? 0);
        if ($pages < 1) $pages = (int) (\ViraSEO\Admin\Dashboard::get('rank_max_pages') ?: 3);
        $pages = max(1, min(10, $pages));

        $t = $wpdb->prefix . 'viraseo_rank_tracking';

        // Self-heal: create the table if a plugin update hasn't migrated it yet
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) {
            (new \ViraSEO\Database\Schema())->create_all_tables();
        }

        $hash = md5(mb_strtolower($kw));
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE keyword_hash=%s", $hash))) {
            wp_send_json_error('این کلمه قبلاً اضافه شده است.');
        }
        $ok = $wpdb->insert($t, [
            'keyword'=>$kw, 'keyword_hash'=>$hash,
            'target_url'=>$target ?: null, 'frequency'=>$freq, 'max_pages'=>$pages, 'status'=>'active',
        ]);
        if ($ok === false) wp_send_json_error('خطای پایگاه داده: ' . ($wpdb->last_error ?: 'جدول rank_tracking ساخته نشد. افزونه را غیرفعال/فعال کنید.'));
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
            // Transparent feedback so the user can see exactly what happened
            if ($res['rank'] !== null) {
                $msg = '✅ رتبه شما: ' . $res['rank'] . ($res['found_url'] ? ' — ' . $res['found_url'] : '');
            } else {
                $reasons = [
                    'end_of_results' => 'نتایج گوگل برای این کلمه تمام شد',
                    'max_pages'      => 'به سقف صفحات تنظیم‌شده رسید',
                    'duplicate'      => 'سرپر صفحه تکراری برگرداند (ورکفلو ۰۴ را دوباره Import کنید)',
                    'error'          => 'خطا در دریافت صفحه بعدی',
                ];
                $why = $reasons[$res['stop'] ?? ''] ?? '';
                $msg = '⚠️ سایت شما (' . implode('، ', $res['hosts']) . ') در ' . $res['total']
                     . ' نتیجه پیدا نشد. ' . $res['pages'] . ' از ' . $res['max_pages'] . ' صفحه بررسی شد'
                     . ($why ? ' (' . $why . ')' : '') . '.';
                if (!empty($res['top'])) $msg .= ' | دامنه‌های نتایج: ' . implode('، ', array_filter($res['top']));
            }
            wp_send_json_success(['message'=>$msg]);
        }
        // check all active
        $ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}viraseo_rank_tracking WHERE status='active'");
        foreach ($ids as $kid) $this->check_keyword((int)$kid);
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
                'pages'=>(int)($r->max_pages ?: 3),
                'freq'=>$freq_fa[$r->frequency] ?? $r->frequency,
                'last'=> $r->last_checked ? JalaliDate::format($r->last_checked, 'relative') : 'هنوز بررسی نشده',
                'history'=> array_values(array_slice((array)json_decode($r->history ?: '[]', true), -14)),
            ];
        }, $rows);
        wp_send_json_success(['rows'=>$data]);
    }

    /** Cron handler: check only keywords whose frequency interval has elapsed. */
    public function run_due(): void {
        // Master switch: skip ALL background checks when auto-monitoring is off (saves Serper credits)
        if (!\ViraSEO\Admin\Dashboard::get('rank_auto_enabled')) return;
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

    /** Query a keyword across up to N pages, stopping as soon as THIS site is found
     *  (minimizes Serper credits — 1 credit per page). Matching is done in PHP. */
    public function check_keyword(int $id): array {
        global $wpdb;
        $t = $wpdb->prefix . 'viraseo_rank_tracking';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id));
        if (!$row) return ['error'=>'یافت نشد.'];

        $hosts = $this->site_hosts();
        $max_pages = max(1, min(10, (int) ($row->max_pages ?: (\ViraSEO\Admin\Dashboard::get('rank_max_pages') ?: 3))));

        $rank = null; $found_url = null; $scanned = 0; $top = []; $pages_used = 0; $prev_first = '';
        $stop = 'max_pages';
        for ($page = 1; $page <= $max_pages; $page++) {
            $fetch = $this->fetch_page($row->keyword, $page);
            if (isset($fetch['error'])) {
                if ($page === 1) return ['error'=>$fetch['error']];
                $stop = 'error'; break; // keep what we have from earlier pages
            }
            $organic = $fetch['organic'];
            if (empty($organic)) { $stop = 'end_of_results'; break; }
            $pages_used = $page;

            // Guard: if Serper ignored the page param and returned the same first result,
            // stop to avoid wasting credits on duplicate pages.
            $first = $this->host_of(($organic[0]['link'] ?? '')) . '|' . (($organic[0]['link'] ?? ''));
            if ($page > 1 && $first === $prev_first) { $stop = 'duplicate'; break; }
            $prev_first = $first;

            foreach ($organic as $item) {
                $scanned++;
                $link = $item['link'] ?? '';
                $h = $this->host_of($link);
                if (count($top) < 10) $top[] = $h ?: '?';
                if ($h && $this->host_match($h, $hosts)) {
                    $rank = $scanned; $found_url = $link; $stop = 'found';
                    break 2; // found — stop paginating (saves credits)
                }
            }
            // Note: we keep paginating up to the per-keyword page count. We only stop
            // early on a completely EMPTY page (handled at the top) or a duplicate page.
        }

        $this->store_result($id, $row, $rank, $found_url);
        return ['rank'=>$rank, 'found_url'=>$found_url, 'total'=>$scanned, 'top'=>$top,
                'hosts'=>$hosts, 'pages'=>$pages_used, 'max_pages'=>$max_pages, 'stop'=>$stop];
    }

    /** Fetch ONE page of organic results (n8n preferred, direct Serper fallback). */
    private function fetch_page(string $keyword, int $page): array {
        if (\ViraSEO\Admin\Dashboard::get('n8n_url')) {
            $res = WebhookHandler::to_n8n('viraseo-rank-check', [
                'keyword'=>$keyword,
                'page'=>$page,
                'site_host'=>$this->site_hosts()[0] ?? '',
                'serper_api_key'=>\ViraSEO\Admin\Dashboard::get('serper_api_key'),
            ]);
            if (!isset($res['error']) && is_array($res['data'] ?? null)) {
                $d = $res['data'];
                if (empty($d['error']) && !empty($d['organic']) && is_array($d['organic'])) {
                    return ['organic'=>$d['organic']];
                }
            }
            // n8n unavailable / old workflow / empty — fall through to direct
        }
        $res = WebhookHandler::serper_search($keyword, 10, $page);
        if (isset($res['error'])) return ['error'=>$res['error']];
        return ['organic'=>$res['organic']];
    }

    /** Candidate host names for THIS site (home + site url, lowercased, www-stripped). */
    private function site_hosts(): array {
        $hosts = [];
        foreach ([home_url(), get_site_url()] as $u) {
            $h = $this->host_of($u);
            if ($h) $hosts[$h] = true;
        }
        return array_keys($hosts) ?: ['']; 
    }

    private function host_of(string $url): string {
        if ($url === '') return '';
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $h = (string) wp_parse_url($url, PHP_URL_HOST);
        return strtolower(preg_replace('/^www\./i', '', $h));
    }

    /** True if result host equals a site host, or is a sub/parent domain of it. */
    private function host_match(string $h, array $site_hosts): bool {
        foreach ($site_hosts as $s) {
            if ($s === '') continue;
            if ($h === $s) return true;
            if (str_ends_with($h, '.' . $s) || str_ends_with($s, '.' . $h)) return true;
        }
        return false;
    }

    /** Persist a rank check result + append to history. */
    private function store_result(int $id, object $row, ?int $rank, ?string $found_url): void {
        global $wpdb;
        $history = (array) json_decode($row->history ?: '[]', true);
        $history[] = ['d'=>JalaliDate::now()['date'], 'r'=>$rank];
        if (count($history) > 60) $history = array_slice($history, -60);

        $best = $row->best_rank;
        if ($rank !== null && ($best === null || $rank < (int)$best)) $best = $rank;

        $wpdb->update($wpdb->prefix . 'viraseo_rank_tracking', [
            'previous_rank'=>$row->current_rank,
            'current_rank'=>$rank,
            'best_rank'=>$best,
            'found_url'=>$found_url,
            'history'=>wp_json_encode($history),
            'last_checked'=>current_time('mysql'),
        ], ['id'=>$id]);

        // Detect a significant rank drop and raise an alert (only after a prior measurement)
        $prev = $row->current_rank;
        if ($prev !== null) {
            $threshold = max(1, (int) (\ViraSEO\Admin\Dashboard::get('rank_alert_threshold') ?: 3));
            $dropped_out = ($rank === null);
            $big_drop = ($rank !== null && ($rank - (int)$prev) >= $threshold);
            if ($dropped_out || $big_drop) {
                $this->raise_alert($row->keyword, (int)$prev, $rank);
            }
        }
    }

    /** Log a rank-drop alert and optionally email the admin. */
    private function raise_alert(string $keyword, int $from, ?int $to): void {
        global $wpdb;
        $to_label = $to === null ? 'خارج از نتایج' : (string) $to;
        $wpdb->insert($wpdb->prefix.'viraseo_activity_log', [
            'action'=>'rank_drop',
            'detail'=>wp_json_encode(['keyword'=>$keyword, 'from'=>$from, 'to'=>$to_label]),
            'user_id'=>get_current_user_id() ?: null,
        ]);
        if (\ViraSEO\Admin\Dashboard::get('rank_alert_email')) {
            $site = get_bloginfo('name');
            $subject = "[{$site}] هشدار افت رتبه: {$keyword}";
            $body = "کلمه کلیدی «{$keyword}» در گوگل افت کرد.\n\n"
                  . "رتبه قبلی: {$from}\nرتبه فعلی: {$to_label}\n\n"
                  . "زمان: " . JalaliDate::now_str() . "\n"
                  . "سایت: " . get_site_url();
            wp_mail(get_option('admin_email'), $subject, $body);
        }
    }
}
