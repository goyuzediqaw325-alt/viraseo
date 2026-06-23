<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Admin\Dashboard;
use ViraSEO\Utils\{JalaliDate, PersianText};

/** Feature 1: GSC Keywords + Striking Distance + Cannibalization [🟢 مستقل] */
class SearchConsole {
    public function __construct() {
        add_action('wp_ajax_viraseo_get_keywords', [$this, 'ajax_keywords']);
        add_action('wp_ajax_viraseo_get_striking', [$this, 'ajax_striking']);
        add_action('wp_ajax_viraseo_get_cannibal', [$this, 'ajax_cannibal']);
        add_action('wp_ajax_viraseo_resolve_cannibal', [$this, 'ajax_resolve']);
        add_action('wp_ajax_viraseo_detect_cannibal', [$this, 'ajax_detect']);
        add_action('wp_ajax_viraseo_gsc_insights', [$this, 'ajax_insights']);
        add_action('wp_ajax_viraseo_gsc_winners', [$this, 'ajax_winners']);
    }

    /**
     * Winners & Losers — compare the latest GSC snapshot to a previous one
     * to surface pages that gained or lost clicks/impressions over time.
     */
    public function ajax_winners(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        $snaps = get_option('viraseo_gsc_snapshots', []);
        if (!is_array($snaps) || count($snaps) < 2) {
            wp_send_json_error('برای مقایسه حداقل به دو همگام‌سازی در زمان‌های مختلف نیاز است. بعداً دوباره داده‌ها را دریافت کنید.');
        }
        $metric = in_array($_POST['metric'] ?? 'clicks', ['clicks','impressions'], true) ? $_POST['metric'] : 'clicks';
        $key = $metric === 'clicks' ? 'c' : 'i';

        $latest = $snaps[count($snaps) - 1];
        // Pick comparison snapshot (default: previous one)
        $back = max(1, absint($_POST['back'] ?? 1));
        $idx = max(0, count($snaps) - 1 - $back);
        $prev = $snaps[$idx];

        $rows = [];
        $urls = array_unique(array_merge(array_keys($latest['pages']), array_keys($prev['pages'])));
        foreach ($urls as $u) {
            $now = (int)($latest['pages'][$u][$key] ?? 0);
            $was = (int)($prev['pages'][$u][$key] ?? 0);
            $delta = $now - $was;
            if ($delta === 0) continue;
            $pid = url_to_postid($u);
            $rows[] = [
                'url'=>$u,
                'title'=> $pid ? (get_the_title($pid) ?: $u) : $u,
                'now'=>PersianText::format_number($now),
                'was'=>PersianText::format_number($was),
                'delta'=>$delta,
                'delta_fa'=>($delta>0?'+':'−').PersianText::format_number(abs($delta)),
                'pos_now'=>JalaliDate::to_fa((string)($latest['pages'][$u]['p'] ?? 0)),
                'pos_was'=>JalaliDate::to_fa((string)($prev['pages'][$u]['p'] ?? 0)),
            ];
        }
        $winners = array_filter($rows, fn($r)=>$r['delta'] > 0);
        $losers = array_filter($rows, fn($r)=>$r['delta'] < 0);
        usort($winners, fn($a,$b)=>$b['delta']<=>$a['delta']);
        usort($losers, fn($a,$b)=>$a['delta']<=>$b['delta']);

        // Snapshot list for the comparison dropdown
        $labels = [];
        foreach ($snaps as $i => $s) $labels[] = ['i'=>$i, 'date'=>JalaliDate::format($s['date'].':00', 'datetime')];

        wp_send_json_success([
            'winners'=>array_slice(array_values($winners), 0, 100),
            'losers'=>array_slice(array_values($losers), 0, 100),
            'latest'=>JalaliDate::format($latest['date'].':00','datetime'),
            'prev'=>JalaliDate::format($prev['date'].':00','datetime'),
            'snapshots'=>$labels,
            'metric'=>$metric,
        ]);
    }

    /**
     * Deep GSC analysis — surfaces three high-value opportunity types:
     *  - CTR anomalies: good rank (≤10) but low CTR → title/meta needs work.
     *  - Quick wins: position 11-20 with impressions → small push to page 1.
     *  - Zero-click high-impression: lots of impressions, no clicks.
     */
    public function ajax_insights(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_gsc_keywords';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) wp_send_json_error('ابتدا داده‌های سرچ کنسول را همگام‌سازی کنید.');

        // Expected CTR by rounded position (approximate Persian/Google curve)
        $expected = [1=>0.28,2=>0.15,3=>0.11,4=>0.08,5=>0.06,6=>0.05,7=>0.04,8=>0.032,9=>0.028,10=>0.025];

        $rows = $wpdb->get_results("SELECT keyword,page_url,clicks,impressions,ctr,position FROM {$t} WHERE impressions >= 30 ORDER BY impressions DESC LIMIT 2000");

        $ctr_ops = []; $quick = []; $zero = [];
        foreach ($rows as $r) {
            $pos = (float)$r->position; $imp = (int)$r->impressions; $clk = (int)$r->clicks; $ctr = (float)$r->ctr;
            $rp = max(1, min(10, (int)round($pos)));

            if ($pos <= 10 && $imp >= 50) {
                $exp = $expected[$rp] ?? 0.02;
                if ($ctr < $exp * 0.6) { // notably under-performing for its position
                    $ctr_ops[] = ['keyword'=>$r->keyword,'url'=>$r->page_url,
                        'impr'=>$imp,'ctr'=>round($ctr*100,1),'exp'=>round($exp*100,1),'pos'=>round($pos,1)];
                }
            }
            if ($pos > 10 && $pos <= 20 && $imp >= 30) {
                $quick[] = ['keyword'=>$r->keyword,'url'=>$r->page_url,'impr'=>$imp,'pos'=>round($pos,1)];
            }
            if ($clk === 0 && $imp >= 100) {
                $zero[] = ['keyword'=>$r->keyword,'url'=>$r->page_url,'impr'=>$imp,'pos'=>round($pos,1)];
            }
        }
        usort($ctr_ops, fn($a,$b)=>$b['impr']<=>$a['impr']);
        usort($quick, fn($a,$b)=>$b['impr']<=>$a['impr']);
        usort($zero, fn($a,$b)=>$b['impr']<=>$a['impr']);

        $fmt = fn($r) => array_map(fn($x)=>array_merge($x, [
            'impr'=>PersianText::format_number($x['impr']),
            'pos'=>JalaliDate::to_fa((string)$x['pos']),
        ]), array_slice($r, 0, 40));

        wp_send_json_success([
            'ctr_ops'=>array_map(fn($x)=>array_merge($fmt([$x])[0], ['ctr'=>JalaliDate::to_fa((string)$x['ctr']).'%','exp'=>JalaliDate::to_fa((string)$x['exp']).'%']), array_slice($ctr_ops,0,40)),
            'quick'=>$fmt($quick),
            'zero'=>$fmt($zero),
        ]);
    }

    public function ajax_keywords(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_gsc_keywords';
        $search = sanitize_text_field($_POST['search']??'');
        $page = max(1,absint($_POST['page']??1));
        $per = 50; $off = ($page-1)*$per;

        // Sortable columns (whitelist) — lets the user sort like Search Console
        $allowed = ['impressions'=>'impressions','clicks'=>'clicks','ctr'=>'ctr','position'=>'position','date'=>'date_recorded'];
        $orderby = $allowed[$_POST['orderby'] ?? ''] ?? 'impressions';
        $order = (strtolower($_POST['order'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

        $where = '';
        $args = [];
        if ($search) {
            $where = 'WHERE keyword LIKE %s';
            $args[] = '%'.$wpdb->esc_like($search).'%';
        }
        $total = $args
            ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} {$where}", $args))
            : $wpdb->get_var("SELECT COUNT(*) FROM {$t}");

        $sql = "SELECT * FROM {$t} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($args, [$per, $off])));

        // Overview totals (across the whole dataset, matching the search filter)
        $sum = $args
            ? $wpdb->get_row($wpdb->prepare("SELECT SUM(clicks) c, SUM(impressions) i, AVG(position) p FROM {$t} {$where}", $args))
            : $wpdb->get_row("SELECT SUM(clicks) c, SUM(impressions) i, AVG(position) p FROM {$t}");

        $data = array_map(fn($r) => [
            'keyword'=>$r->keyword, 'page_url'=>$r->page_url,
            'clicks'=>PersianText::format_number($r->clicks),
            'impressions'=>PersianText::format_number($r->impressions),
            'ctr'=>JalaliDate::to_fa(number_format($r->ctr*100,2)).'%',
            'position'=>JalaliDate::to_fa(number_format($r->position,1)),
            'date'=>JalaliDate::format($r->date_recorded),
            'is_striking'=>(bool)$r->is_striking,
        ], $rows ?: []);

        wp_send_json_success([
            'rows'=>$data,'total'=>(int)$total,'pages'=>ceil($total/$per),
            'orderby'=>array_search($orderby, $allowed, true), 'order'=>strtolower($order),
            'totals'=>[
                'clicks'=>PersianText::format_number((int)($sum->c ?? 0)),
                'impressions'=>PersianText::format_number((int)($sum->i ?? 0)),
                'avg_position'=>JalaliDate::to_fa(number_format((float)($sum->p ?? 0),1)),
                'count'=>PersianText::format_number((int)$total),
            ],
        ]);
    }

    public function ajax_striking(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_gsc_keywords';
        $s = Dashboard::get();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT keyword, page_url, impressions, clicks, position FROM {$t}
             WHERE position BETWEEN %f AND %f AND impressions >= %d
             ORDER BY impressions DESC LIMIT 50",
            (float)$s['striking_min'], (float)$s['striking_max'], (int)$s['min_impressions']
        ));
        $data = array_map(fn($r) => [
            'keyword'=>$r->keyword,'page_url'=>$r->page_url,
            'impressions'=>PersianText::format_number($r->impressions),
            'clicks'=>PersianText::format_number($r->clicks),
            'position'=>JalaliDate::to_fa(number_format($r->position,1)),
        ], $rows);
        wp_send_json_success(['rows'=>$data,'count'=>count($data)]);
    }

    public function ajax_cannibal(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_cannibalization';
        $rows = $wpdb->get_results("SELECT * FROM {$t} WHERE status='detected' ORDER BY FIELD(severity,'critical','warning','info') LIMIT 50");
        $data = array_map(fn($r) => [
            'id'=>$r->id,'keyword'=>$r->keyword,'severity'=>$r->severity,
            'page_1'=>['url'=>$r->page_url_1,'pos'=>$r->position_1,'imp'=>$r->impressions_1],
            'page_2'=>['url'=>$r->page_url_2,'pos'=>$r->position_2,'imp'=>$r->impressions_2],
            'recommendation'=>$r->recommended_action,
            'detected'=>JalaliDate::format($r->detected_at,'relative'),
        ], $rows);
        wp_send_json_success(['rows'=>$data]);
    }

    public function ajax_resolve(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $id = absint($_POST['id']??0);
        $status = sanitize_text_field($_POST['status']??'resolved');
        if ($id) $wpdb->update($wpdb->prefix.'viraseo_cannibalization', ['status'=>$status], ['id'=>$id]);
        wp_send_json_success();
    }

    /** Auto-detect cannibalization from existing keyword data */
    public function ajax_detect(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $kt = $wpdb->prefix.'viraseo_gsc_keywords';
        $ct = $wpdb->prefix.'viraseo_cannibalization';

        // Find keywords ranking on multiple pages
        $conflicts = $wpdb->get_results(
            "SELECT keyword_hash, keyword, GROUP_CONCAT(DISTINCT page_url) as pages,
                    GROUP_CONCAT(DISTINCT position) as positions,
                    GROUP_CONCAT(DISTINCT impressions) as imps
             FROM {$kt}
             WHERE impressions >= 10
             GROUP BY keyword_hash
             HAVING COUNT(DISTINCT page_url_hash) >= 2
             LIMIT 100"
        );

        $ins = 0;
        foreach ($conflicts as $c) {
            if ($wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$ct} WHERE keyword_hash=%s AND status='detected'", $c->keyword_hash))) continue;

            $pages = explode(',', $c->pages);
            $positions = explode(',', $c->positions);
            $imps = explode(',', $c->imps);

            $diff = abs(($positions[0]??0) - ($positions[1]??0));
            $severity = $diff <= 3 ? 'critical' : ($diff <= 7 ? 'warning' : 'info');

            $wpdb->insert($ct, [
                'keyword'=>$c->keyword,'keyword_hash'=>$c->keyword_hash,
                'page_url_1'=>$pages[0]??'','position_1'=>$positions[0]??0,'impressions_1'=>$imps[0]??0,
                'page_url_2'=>$pages[1]??'','position_2'=>$positions[1]??0,'impressions_2'=>$imps[1]??0,
                'severity'=>$severity,
                'recommended_action'=>$severity==='critical'?'merge':($severity==='warning'?'canonical':'differentiate'),
            ]);
            $ins++;
        }
        wp_send_json_success(['detected'=>$ins]);
    }
}
