<?php
namespace ViraSEO\Api;
defined('ABSPATH') || exit;

use ViraSEO\Admin\Dashboard;

/**
 * REST API endpoints for receiving data FROM n8n
 * + Static methods for sending requests TO n8n
 */
class WebhookHandler {
    public function __construct() {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void {
        $ns = 'viraseo/v1';
        $perm = [$this, 'verify'];
        register_rest_route($ns, '/serp-results', ['methods'=>'POST','callback'=>[$this,'handle_serp'],'permission_callback'=>$perm]);
        register_rest_route($ns, '/cannibalization', ['methods'=>'POST','callback'=>[$this,'handle_cannibal'],'permission_callback'=>$perm]);
        register_rest_route($ns, '/keyword-ideas', ['methods'=>'POST','callback'=>[$this,'handle_ideas'],'permission_callback'=>$perm]);
    }

    public function verify(\WP_REST_Request $r): bool {
        $secret = Dashboard::get('n8n_secret');
        if (!$secret) return false;
        return hash_equals($secret, (string)$r->get_header('X-ViraSEO-Secret'));
    }

    public function handle_serp(\WP_REST_Request $r): \WP_REST_Response {
        global $wpdb;
        $d = $r->get_json_params();
        $id = absint($d['analysis_id'] ?? 0);
        if (!$id) return new \WP_REST_Response(['error'=>'no id'],400);

        $t = $wpdb->prefix.'viraseo_serp_analysis';
        $wpdb->update($t, [
            'status'=>'completed','completed_at'=>current_time('mysql'),
            'avg_word_count'=>absint($d['avg_word_count']??0),
            'avg_headings'=>absint($d['avg_headings']??0),
            'lsi_keywords'=>wp_json_encode($d['lsi_keywords']??[]),
            'content_gap'=>wp_json_encode($d['content_gap']??[]),
            'questions'=>wp_json_encode($d['questions']??[]),
            'ecommerce_data'=>wp_json_encode($d['ecommerce_data']??null),
        ], ['id'=>$id]);

        $ct = $wpdb->prefix.'viraseo_serp_competitors';
        foreach (($d['competitors']??[]) as $c) {
            $wpdb->insert($ct, [
                'analysis_id'=>$id,'position'=>absint($c['position']??0),
                'url'=>esc_url_raw($c['url']??''),'title'=>sanitize_text_field($c['title']??''),
                'domain'=>sanitize_text_field($c['domain']??''),
                'word_count'=>absint($c['word_count']??0),
                'h1_count'=>absint($c['h1_count']??0),'h2_count'=>absint($c['h2_count']??0),'h3_count'=>absint($c['h3_count']??0),
                'images_count'=>absint($c['images']??0),'schema_types'=>sanitize_text_field($c['schema_types']??''),
            ]);
        }
        return new \WP_REST_Response(['success'=>true],200);
    }

    public function handle_cannibal(\WP_REST_Request $r): \WP_REST_Response {
        global $wpdb;
        $d = $r->get_json_params();
        $t = $wpdb->prefix.'viraseo_cannibalization';
        $ins = 0;
        foreach (($d['conflicts']??[]) as $c) {
            $kh = md5(mb_strtolower($c['keyword']??''));
            if ($wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t} WHERE keyword_hash=%s AND status='detected'",$kh))) continue;
            $wpdb->insert($t, [
                'keyword'=>sanitize_text_field($c['keyword']??''),'keyword_hash'=>$kh,
                'page_url_1'=>esc_url_raw($c['page_1']['url']??''),
                'position_1'=>floatval($c['page_1']['position']??0),'impressions_1'=>absint($c['page_1']['impressions']??0),
                'page_url_2'=>esc_url_raw($c['page_2']['url']??''),
                'position_2'=>floatval($c['page_2']['position']??0),'impressions_2'=>absint($c['page_2']['impressions']??0),
                'severity'=>sanitize_text_field($c['severity']??'info'),
                'recommended_action'=>sanitize_text_field($c['recommendation']??''),
            ]);
            $ins++;
        }
        return new \WP_REST_Response(['success'=>true,'inserted'=>$ins],200);
    }

    public function handle_ideas(\WP_REST_Request $r): \WP_REST_Response {
        global $wpdb;
        $d = $r->get_json_params();
        $disc_id = sanitize_text_field($d['discovery_id']??'');
        if (!$disc_id) return new \WP_REST_Response(['error'=>'no discovery_id'],400);

        $t = $wpdb->prefix.'viraseo_keyword_ideas';
        $ins = 0;
        foreach (($d['ideas']??[]) as $idea) {
            $kw = sanitize_text_field($idea['keyword']??'');
            if (!$kw) continue;
            $wpdb->insert($t, [
                'discovery_id'=>$disc_id,'keyword'=>$kw,
                'source'=>in_array($idea['source']??'',['suggest','related','paa'])?$idea['source']:'suggest',
                'relevance'=>min(100,absint($idea['relevance']??50)),
                'is_question'=>!empty($idea['is_question'])?1:0,
            ]);
            $ins++;
        }
        $wpdb->update($wpdb->prefix.'viraseo_keyword_discoveries',
            ['status'=>'completed','ideas_count'=>$ins,'completed_at'=>current_time('mysql')],
            ['discovery_id'=>$disc_id]
        );
        return new \WP_REST_Response(['success'=>true,'inserted'=>$ins],200);
    }

    // === OUTGOING TO N8N ===

    public static function to_n8n(string $path, array $body): array {
        $url = Dashboard::get('n8n_url');
        $secret = Dashboard::get('n8n_secret');
        if (!$url) return ['error'=>'n8n تنظیم نشده.'];

        $r = wp_remote_post($url.'/webhook/'.$path, [
            'timeout'=>30,
            'headers'=>['Content-Type'=>'application/json','X-ViraSEO-Secret'=>$secret],
            'body'=>wp_json_encode($body),
        ]);
        if (is_wp_error($r)) return ['error'=>$r->get_error_message()];
        $code = wp_remote_retrieve_response_code($r);
        if ($code >= 200 && $code < 300) return ['success'=>true,'data'=>json_decode(wp_remote_retrieve_body($r),true)];
        return ['error'=>"HTTP {$code}"];
    }

    public static function send_serp_request(string $keyword, int $user_id): array {
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_serp_analysis';
        $wpdb->insert($t, ['keyword'=>$keyword,'keyword_hash'=>md5(mb_strtolower($keyword)),'status'=>'pending','requested_by'=>$user_id]);
        $id = $wpdb->insert_id;
        if (!$id) return ['error'=>'DB error'];

        $result = self::to_n8n('viraseo-serp-analyze', [
            'keyword'=>$keyword,'analysis_id'=>$id,
            'callback_url'=>rest_url('viraseo/v1/serp-results'),
            'site_url'=>get_site_url(),'language'=>'fa',
        ]);

        if (isset($result['error'])) {
            $wpdb->update($t, ['status'=>'failed'], ['id'=>$id]);
            return ['error'=>$result['error'],'analysis_id'=>$id];
        }
        $wpdb->update($t, ['status'=>'processing'], ['id'=>$id]);
        return ['success'=>true,'analysis_id'=>$id];
    }

    public static function send_discovery_request(string $seed, string $disc_id): array {
        return self::to_n8n('viraseo-keyword-discover', [
            'seed_keyword'=>$seed,'discovery_id'=>$disc_id,
            'callback_url'=>rest_url('viraseo/v1/keyword-ideas'),
            'language'=>'fa',
        ]);
    }
}
