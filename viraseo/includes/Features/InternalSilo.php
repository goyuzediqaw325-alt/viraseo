<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\PersianText;

/** Feature 3: Internal Links + Orphan Pages + Suggestions [🟢 مستقل] */
class InternalSilo {
    public function __construct() {
        add_action('viraseo_scan_orphan_pages', [$this, 'scan']);
        add_action('viraseo_generate_link_suggestions', [$this, 'suggest']);
        add_action('wp_ajax_viraseo_trigger_scan', [$this, 'ajax_scan']);
        add_action('wp_ajax_viraseo_get_orphans', [$this, 'ajax_orphans']);
        add_action('wp_ajax_viraseo_get_suggestions', [$this, 'ajax_suggestions']);
        add_action('wp_ajax_viraseo_accept_link', [$this, 'ajax_accept']);
        add_action('wp_ajax_viraseo_reject_link', [$this, 'ajax_reject']);
    }

    public function ajax_scan(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        try {
            $result = $this->scan();
            wp_send_json_success([
                'message' => sprintf('✅ اسکن کامل شد. %d لینک داخلی یافت شد. %d صفحه یتیم شناسایی شد.', $result['links'], $result['orphans']),
                'links' => $result['links'],
                'orphans' => $result['orphans'],
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error('خطا در اسکن: ' . $e->getMessage());
        }
    }

    public function ajax_orphans(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_orphan_pages WHERE status IN ('orphan','low') ORDER BY inlinks LIMIT 50");
        $data = array_map(fn($r)=>[
            'id'=>$r->post_id,'title'=>$r->post_title?:get_the_title($r->post_id),
            'type'=>$r->post_type,'inlinks'=>(int)$r->inlinks,'outlinks'=>(int)$r->outlinks,
            'status'=>$r->status,'url'=>get_permalink($r->post_id),'edit'=>get_edit_post_link($r->post_id,'raw'),
        ], $rows);
        wp_send_json_success(['rows'=>$data]);
    }

    public function ajax_suggestions(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_link_suggestions WHERE status='pending' ORDER BY score DESC LIMIT 30");
        $data = array_map(fn($r)=>[
            'id'=>$r->id,
            'source'=>get_the_title($r->source_id),'source_edit'=>get_edit_post_link($r->source_id,'raw'),
            'target'=>get_the_title($r->target_id),'target_url'=>get_permalink($r->target_id),
            'anchor'=>$r->anchor,'score'=>(float)$r->score,'reason'=>$r->reason,
        ], $rows);
        wp_send_json_success(['rows'=>$data]);
    }

    public function ajax_accept(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $id = absint($_POST['id']??0);
        if ($id) $wpdb->update($wpdb->prefix.'viraseo_link_suggestions', ['status'=>'accepted'], ['id'=>$id]);
        wp_send_json_success();
    }

    public function ajax_reject(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $id = absint($_POST['id']??0);
        if ($id) $wpdb->update($wpdb->prefix.'viraseo_link_suggestions', ['status'=>'rejected'], ['id'=>$id]);
        wp_send_json_success();
    }

    public function scan(): array {
        global $wpdb;
        $lt = $wpdb->prefix.'viraseo_internal_links';
        $ot = $wpdb->prefix.'viraseo_orphan_pages';
        $host = wp_parse_url(get_site_url(), PHP_URL_HOST);

        $posts = $wpdb->get_results("SELECT ID,post_title,post_content,post_type FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') LIMIT 500");
        if (!$posts) return ['links'=>0,'orphans'=>0];

        $wpdb->query("DELETE FROM {$lt}");
        $link_count = 0;
        foreach ($posts as $p) {
            if (empty($p->post_content)) continue;
            preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $p->post_content, $m, PREG_SET_ORDER);
            foreach ($m as $match) {
                $href = $match[1]; $anchor = wp_strip_all_tags($match[2]);
                if (strpos($href,'/')===0) $href = get_site_url().$href;
                $lh = wp_parse_url($href, PHP_URL_HOST);
                if (!$lh || $lh !== $host) continue;
                $tid = url_to_postid($href);
                if (!$tid || $tid === $p->ID) continue;
                $wpdb->insert($lt, ['source_id'=>$p->ID,'target_id'=>$tid,'anchor'=>mb_substr($anchor,0,500),'link_url'=>$href]);
                $link_count++;
            }
        }

        $wpdb->query("DELETE FROM {$ot}");
        $wpdb->query("
            INSERT INTO {$ot} (post_id,post_type,post_title,inlinks,outlinks,status)
            SELECT p.ID, p.post_type, p.post_title,
                   COALESCE(i.c,0), COALESCE(o.c,0),
                   CASE WHEN COALESCE(i.c,0)=0 THEN 'orphan' WHEN COALESCE(i.c,0)<=2 THEN 'low' ELSE 'ok' END
            FROM {$wpdb->posts} p
            LEFT JOIN (SELECT target_id,COUNT(*) c FROM {$lt} GROUP BY target_id) i ON i.target_id=p.ID
            LEFT JOIN (SELECT source_id,COUNT(*) c FROM {$lt} GROUP BY source_id) o ON o.source_id=p.ID
            WHERE p.post_status='publish' AND p.post_type IN ('post','page','product') AND COALESCE(i.c,0)<=2
        ");

        $orphan_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$ot} WHERE status='orphan'");
        update_option('viraseo_last_scan', current_time('mysql'));

        return ['links'=>$link_count, 'orphans'=>$orphan_count];
    }

    public function suggest(): void {
        global $wpdb;
        $st = $wpdb->prefix.'viraseo_link_suggestions';
        $lt = $wpdb->prefix.'viraseo_internal_links';
        $posts = $wpdb->get_results("SELECT ID,post_title,post_content FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page') LIMIT 200");
        if (count($posts)<2) return;

        $cache = [];
        foreach ($posts as $p) $cache[$p->ID] = PersianText::extract_keywords(wp_strip_all_tags($p->post_content), 20);

        $count = 0;
        for ($i=0; $i<count($posts) && $count<100; $i++) {
            for ($j=$i+1; $j<count($posts) && $count<100; $j++) {
                $a=$posts[$i]; $b=$posts[$j];
                if ($wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$lt} WHERE source_id=%d AND target_id=%d",$a->ID,$b->ID))) continue;
                $ka=array_keys($cache[$a->ID]); $kb=array_keys($cache[$b->ID]);
                $shared=array_intersect($ka,$kb); $union=array_unique(array_merge($ka,$kb));
                if (empty($union)) continue;
                $sim = count($shared)/count($union);
                if ($sim < 0.12) continue;
                $anchor = $shared ? reset($shared) : $b->post_title;
                if ($wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$st} WHERE source_id=%d AND target_id=%d",$a->ID,$b->ID))) continue;
                $wpdb->insert($st, ['source_id'=>$a->ID,'target_id'=>$b->ID,'anchor'=>mb_substr($anchor,0,500),'score'=>round($sim*100,2),'reason'=>'شباهت '.round($sim*100).'%']);
                $count++;
            }
        }
    }
}
