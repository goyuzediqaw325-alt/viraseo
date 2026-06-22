<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Api\WebhookHandler;
use ViraSEO\Utils\{JalaliDate, PersianText};

/** Feature 2: SERP Competitor Intelligence [🔵 نیازمند n8n] */
class SerpAnalyzer {
    public function __construct() {
        add_action('wp_ajax_viraseo_start_serp', [$this, 'ajax_start']);
        add_action('wp_ajax_viraseo_serp_status', [$this, 'ajax_status']);
        add_action('wp_ajax_viraseo_serp_results', [$this, 'ajax_results']);
    }

    public function ajax_start(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        $kw = sanitize_text_field($_POST['keyword']??'');
        if (!$kw) wp_send_json_error('کلمه کلیدی وارد کنید.');
        $kw = PersianText::normalize($kw);
        $r = WebhookHandler::send_serp_request($kw, get_current_user_id());
        if (isset($r['error'])) wp_send_json_error($r['error']);
        wp_send_json_success(['analysis_id'=>$r['analysis_id'],'message'=>'تحلیل شروع شد...']);
    }

    public function ajax_status(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $id = absint($_POST['analysis_id']??0);
        $st = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}viraseo_serp_analysis WHERE id=%d",$id));
        wp_send_json_success(['status'=>$st??'unknown']);
    }

    public function ajax_results(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $id = absint($_POST['analysis_id']??0);
        $a = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}viraseo_serp_analysis WHERE id=%d",$id));
        if (!$a || $a->status!=='completed') { wp_send_json_success(['status'=>$a->status??'unknown']); return; }

        $comps = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}viraseo_serp_competitors WHERE analysis_id=%d ORDER BY position", $id
        ));

        wp_send_json_success([
            'status'=>'completed',
            'keyword'=>$a->keyword,
            'avg_words'=>PersianText::format_number($a->avg_word_count),
            'avg_headings'=>$a->avg_headings,
            'lsi'=>json_decode($a->lsi_keywords?:'[]',true),
            'gap'=>json_decode($a->content_gap?:'[]',true),
            'questions'=>json_decode($a->questions?:'[]',true),
            'ecommerce'=>json_decode($a->ecommerce_data?:'null',true),
            'competitors'=>array_map(fn($c)=>[
                'pos'=>$c->position,'url'=>$c->url,'domain'=>$c->domain,'title'=>$c->title,
                'words'=>$c->word_count,'h1'=>$c->h1_count,'h2'=>$c->h2_count,'h3'=>$c->h3_count,
                'images'=>$c->images_count,'schema'=>$c->schema_types,
            ], $comps),
        ]);
    }
}
