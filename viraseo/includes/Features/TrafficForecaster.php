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
        $target = max(1,min(20,absint($_POST['target']??5)));
        $target_ctr = (self::CTR[$target]??0.6) / 100;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT keyword, page_url, SUM(clicks) c, SUM(impressions) i, AVG(position) p
             FROM {$t} WHERE date_recorded>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)
             AND position BETWEEN 11 AND 30 AND impressions>=10
             GROUP BY keyword_hash,page_url_hash ORDER BY i DESC LIMIT 80"
        ));

        $data = [];
        foreach ($rows as $r) {
            $potential = (int)round($r->i * $target_ctr);
            $growth = max(0, $potential - (int)$r->c);
            $data[] = [
                'keyword'=>$r->keyword,'url'=>$r->page_url,
                'position'=>JalaliDate::to_fa(number_format($r->p,1)),
                'impressions'=>PersianText::format_number($r->i),
                'clicks'=>PersianText::format_number($r->c),
                'potential'=>PersianText::format_number($potential),
                'growth'=>PersianText::format_number($growth),
                'growth_raw'=>$growth,
            ];
        }
        usort($data, fn($a,$b)=>$b['growth_raw']<=>$a['growth_raw']);

        wp_send_json_success([
            'rows'=>$data,
            'target'=>$target,
            'target_ctr'=>self::CTR[$target].'%',
            'total_growth'=>PersianText::format_number(array_sum(array_column($data,'growth_raw'))),
        ]);
    }
}
