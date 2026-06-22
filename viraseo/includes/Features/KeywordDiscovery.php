<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Api\WebhookHandler;
use ViraSEO\Utils\PersianText;

/** Feature 9: Keyword Discovery via n8n [🔵 نیازمند n8n] */
class KeywordDiscovery {
    public function __construct() {
        add_action('wp_ajax_viraseo_discover', [$this, 'ajax_start']);
        add_action('wp_ajax_viraseo_disc_ideas', [$this, 'ajax_ideas']);
        add_action('wp_ajax_viraseo_disc_brief', [$this, 'ajax_brief']);
    }

    public function ajax_start(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        $seed = sanitize_text_field($_POST['seed']??'');
        if (mb_strlen($seed)<2) wp_send_json_error('حداقل ۲ حرف.');
        $seed = PersianText::normalize($seed);

        global $wpdb;
        $disc_id = md5($seed.time());
        $wpdb->insert($wpdb->prefix.'viraseo_keyword_discoveries', [
            'discovery_id'=>$disc_id,'seed'=>$seed,'status'=>'processing',
        ]);

        $r = WebhookHandler::send_discovery_request($seed, $disc_id);
        if (isset($r['error'])) {
            $wpdb->update($wpdb->prefix.'viraseo_keyword_discoveries',['status'=>'failed'],['discovery_id'=>$disc_id]);
            wp_send_json_error($r['error']);
        }
        wp_send_json_success(['discovery_id'=>$disc_id,'message'=>'جستجو شروع شد...']);
    }

    public function ajax_ideas(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $did = sanitize_text_field($_POST['discovery_id']??'');
        $disc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}viraseo_keyword_discoveries WHERE discovery_id=%s",$did));
        if (!$disc) wp_send_json_error('یافت نشد.');
        if ($disc->status==='processing') { wp_send_json_success(['status'=>'processing']); return; }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}viraseo_keyword_ideas WHERE discovery_id=%s AND status='active' ORDER BY relevance DESC LIMIT 150",$did
        ));
        $data = array_map(fn($r)=>['id'=>$r->id,'keyword'=>$r->keyword,'source'=>$r->source,'relevance'=>$r->relevance,'question'=>(bool)$r->is_question], $rows);
        wp_send_json_success(['status'=>'completed','seed'=>$disc->seed,'rows'=>$data]);
    }

    public function ajax_brief(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $ids = array_map('absint', (array)($_POST['ids']??[]));
        if (!$ids) wp_send_json_error('انتخاب کنید.');
        $ph = implode(',',array_fill(0,count($ids),'%d'));
        $kws = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}viraseo_keyword_ideas WHERE id IN ({$ph})",...$ids));
        if (!$kws) wp_send_json_error('یافت نشد.');

        $primary = $kws[0]->keyword;
        $content = "<!-- بریف محتوا - ویرا سئو -->\n<h2>{$primary}</h2>\n<p>[مقدمه ۲۰۰ کلمه]</p>\n\n";
        $qs = []; $topics = [];
        foreach ($kws as $k) { if ($k->is_question) $qs[]=$k->keyword; else $topics[]=$k->keyword; }
        foreach (array_slice($topics,1,6) as $t) $content .= "<h2>{$t}</h2>\n<p>[۲۰۰-۳۰۰ کلمه]</p>\n\n";
        if ($qs) { $content .= "<h2>سؤالات متداول</h2>\n"; foreach (array_slice($qs,0,6) as $q) $content .= "<h3>{$q}</h3>\n<p>[پاسخ]</p>\n\n"; }
        $content .= "<h2>جمع‌بندی</h2>\n<p>[نتیجه‌گیری]</p>\n";

        $post_id = wp_insert_post(['post_title'=>$primary,'post_content'=>$content,'post_status'=>'draft','post_author'=>get_current_user_id()]);
        if (is_wp_error($post_id)) wp_send_json_error('خطا در ایجاد پست.');

        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}viraseo_keyword_ideas SET status='used' WHERE id IN ({$ph})",...$ids));
        wp_send_json_success(['message'=>"پیش‌نویس «{$primary}» ایجاد شد.",'edit_url'=>get_edit_post_link($post_id,'raw')]);
    }
}
