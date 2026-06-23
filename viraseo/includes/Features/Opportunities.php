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

    public function ajax_link_opportunities(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $gsc = $this->gsc_by_post();
        if (!$gsc) wp_send_json_error('ابتدا داده‌های سرچ کنسول را همگام‌سازی و سپس «اسکن لینک‌ها» را اجرا کنید.');
        $inlinks = $this->inlinks_by_post();

        $rows = [];
        foreach ($gsc as $pid => $g) {
            $in = $inlinks[$pid] ?? 0;
            // Opportunity = decent impressions but ≤ 3 internal inbound links
            if ($g['impr'] < 50 || $in > 3) continue;
            $rows[] = [
                'id'=>$pid,
                'title'=>get_the_title($pid) ?: '(بدون عنوان)',
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
        wp_send_json_success(['rows'=>array_slice($rows, 0, 300)]);
    }

    public function ajax_thin_content(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;

        $threshold = max(50, absint($_POST['threshold'] ?? 300));
        $gsc = $this->gsc_by_post();
        $posts = $wpdb->get_results("SELECT ID, post_title, post_content, post_type FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') LIMIT 1000");

        $rows = [];
        foreach ($posts as $p) {
            $wc = PersianText::word_count(wp_strip_all_tags($p->post_content));
            if ($wc >= $threshold) continue;
            $impr = $gsc[$p->ID]['impr'] ?? 0;
            $rows[] = [
                'id'=>$p->ID,
                'title'=>$p->post_title ?: '(بدون عنوان)',
                'type'=>$p->post_type,
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
        wp_send_json_success(['rows'=>array_slice($rows, 0, 300), 'threshold'=>$threshold]);
    }
}
