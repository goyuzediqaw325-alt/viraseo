<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Admin\Dashboard;
use ViraSEO\Utils\{JalaliDate, PersianText};

/** Feature 8: Traffic ROI Forecaster [🟢 مستقل] */
class TrafficForecaster {
    private const CTR = [1=>31.7,2=>24.7,3=>18.6,4=>13.2,5=>9.5,6=>6.3,7=>4.4,8=>3.3,9=>2.8,10=>2.5,11=>2.2,12=>1.9,13=>1.6,14=>1.4,15=>1.2,16=>1.0,17=>0.9,18=>0.8,19=>0.7,20=>0.6];

    public function __construct() {
        add_action('wp_ajax_viraseo_forecast', [$this, 'ajax']);
    }

    public function ajax(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_gsc_keywords';
        $target = max(1,min(20,absint($_POST['target']??3)));
        $target_ctr = (self::CTR[$target]??0.6) / 100;

        // Get keywords ranked 4-30 (page 1 bottom to page 3) with impressions
        // These are the "opportunity" keywords — ranking but not winning
        $rows = $wpdb->get_results(
            "SELECT keyword, page_url, SUM(clicks) c, SUM(impressions) i, AVG(position) p
             FROM {$t}
             WHERE position BETWEEN 4 AND 30 AND impressions >= 5
             GROUP BY keyword_hash, page_url_hash
             ORDER BY i DESC LIMIT 150"
        );

        $data = [];
        foreach ($rows as $r) {
            $curPos = round($r->p, 1);
            $curClicks = (int)$r->c;
            $impr = (int)$r->i;

            // Potential at target rank
            $potential = (int)round($impr * $target_ctr);
            $growth = max(0, $potential - $curClicks);

            // Effort estimate: how far to climb
            $gap = $curPos - $target;
            $effort = $gap <= 2 ? 'آسان' : ($gap <= 5 ? 'متوسط' : 'سخت');
            $effortColor = $gap <= 2 ? 'green' : ($gap <= 5 ? 'orange' : 'red');

            // Priority: high growth + low effort = high priority
            $priority = $growth / max(1, $gap);

            $data[] = [
                'keyword'=>$r->keyword,'url'=>$r->page_url,
                'position'=>JalaliDate::to_fa(number_format($curPos,1)),
                'position_raw'=>$curPos,
                'impressions'=>PersianText::format_number($impr),
                'clicks'=>PersianText::format_number($curClicks),
                'potential'=>PersianText::format_number($potential),
                'growth'=>'+'.PersianText::format_number($growth),
                'growth_raw'=>$growth,
                'effort'=>$effort,
                'effort_color'=>$effortColor,
                'priority'=>$priority,
            ];
        }

        // Sort by priority (best opportunities first)
        usort($data, fn($a,$b)=>$b['priority']<=>$a['priority']);
        $data = array_slice($data, 0, 80);

        wp_send_json_success([
            'rows'=>$data,
            'target'=>$target,
            'target_ctr'=>self::CTR[$target].'%',
            'total_growth'=>PersianText::format_number(array_sum(array_column($data,'growth_raw'))),
            'count'=>count($data),
        ]);
    }
}
