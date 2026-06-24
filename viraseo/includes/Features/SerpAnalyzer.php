<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Api\WebhookHandler;
use ViraSEO\Admin\Dashboard;
use ViraSEO\Utils\{JalaliDate, PersianText};

/** Feature 2: SERP Competitor Intelligence [🔵 نیازمند n8n] */
class SerpAnalyzer {
    public function __construct() {
        add_action('wp_ajax_viraseo_start_serp', [$this, 'ajax_start']);
        add_action('wp_ajax_viraseo_serp_status', [$this, 'ajax_status']);
        add_action('wp_ajax_viraseo_serp_results', [$this, 'ajax_results']);
        add_action('wp_ajax_viraseo_serp_history', [$this, 'ajax_history']);
        add_action('wp_ajax_viraseo_serp_deep_save', [$this, 'ajax_deep_save']);
    }

    /**
     * Persist REAL per-competitor metrics (from deep page inspection of all 10 results),
     * recompute averages + a "how to win" recommendation, and sync depth data to the page.
     */
    public function ajax_deep_save(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $id = absint($_POST['analysis_id'] ?? 0);
        $items = json_decode(wp_unslash($_POST['items'] ?? '[]'), true);
        if (!$id || !is_array($items) || !$items) wp_send_json_error('داده ناقص.');

        $ct = $wpdb->prefix.'viraseo_serp_competitors';
        $words = []; $heads = [];
        foreach ($items as $it) {
            $url = esc_url_raw($it['url'] ?? '');
            if (!$url) continue;
            $wc = absint($it['word_count'] ?? 0);
            $h1 = absint($it['h1'] ?? 0); $h2 = absint($it['h2'] ?? 0); $h3 = absint($it['h3'] ?? 0);
            $img = absint($it['images'] ?? 0);
            if ($wc > 0) { $words[] = $wc; $heads[] = $h1 + $h2 + $h3; }
            $wpdb->update($ct, [
                'word_count'=>$wc, 'h1_count'=>$h1, 'h2_count'=>$h2, 'h3_count'=>$h3, 'images_count'=>$img,
            ], ['analysis_id'=>$id, 'url'=>$url]);
        }

        $avg_words = $words ? (int) round(array_sum($words) / count($words)) : 0;
        $max_words = $words ? max($words) : 0;
        $avg_heads = $heads ? (int) round(array_sum($heads) / count($heads)) : 0;
        $target_words = $max_words ? (int) round($max_words * 1.1) : $avg_words; // beat the longest

        $a = $wpdb->get_row($wpdb->prepare("SELECT keyword, post_id, content_gap, lsi_keywords FROM {$wpdb->prefix}viraseo_serp_analysis WHERE id=%d", $id));
        $wpdb->update($wpdb->prefix.'viraseo_serp_analysis', ['avg_word_count'=>$avg_words, 'avg_headings'=>$avg_heads], ['id'=>$id]);

        $rec = sprintf('برای پیشی‌گرفتن از رقبا: محتوایی حدود %s کلمه (رقیب برتر: %s) با حداقل %s هدینگ بنویسید.',
            PersianText::format_number($target_words), PersianText::format_number($max_words), PersianText::format_number(max(1,$avg_heads)));

        // Sync depth metrics to the target page (used by Target Keywords / On-Page)
        if ($a && $a->post_id) {
            $si = get_post_meta((int)$a->post_id, '_viraseo_serp_intent', true);
            if (!is_array($si)) $si = [];
            $si['avg_words'] = $avg_words;
            $si['target_words'] = $target_words;
            $si['avg_headings'] = $avg_heads;
            $si['analyzed_deep'] = true;
            update_post_meta((int)$a->post_id, '_viraseo_serp_intent', $si);
        }

        wp_send_json_success([
            'avg_words'=>PersianText::format_number($avg_words),
            'target_words'=>PersianText::format_number($target_words),
            'max_words'=>PersianText::format_number($max_words),
            'avg_headings'=>PersianText::format_number($avg_heads),
            'recommendation'=>$rec,
        ]);
    }

    public function ajax_start(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        $kw = sanitize_text_field($_POST['keyword']??'');
        if (!$kw) wp_send_json_error('کلمه کلیدی وارد کنید.');

        $kw = PersianText::normalize($kw);
        $post_id = absint($_POST['post_id'] ?? 0);

        // Respect the serp_mode setting
        $serp_mode = Dashboard::get('serp_mode') ?: 'direct';

        $r = WebhookHandler::send_serp_request($kw, get_current_user_id(), $post_id, $serp_mode);
        if (isset($r['error'])) wp_send_json_error('❌ خطا: ' . $r['error']);
        wp_send_json_success(['analysis_id'=>$r['analysis_id'],'message'=>'✅ تحلیل انجام شد.']);
    }

    /** List recent SERP analyses (persistent history). */
    public function ajax_history(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT id,keyword,status,avg_word_count,intent_data,completed_at,requested_at FROM {$wpdb->prefix}viraseo_serp_analysis ORDER BY id DESC LIMIT 20");
        $labels = ['product'=>'محصول','article'=>'مقاله','service'=>'خدماتی'];
        $data = array_map(function($r) use ($labels) {
            $intent = json_decode($r->intent_data ?: 'null', true);
            return [
                'id'=>(int)$r->id,
                'keyword'=>$r->keyword,
                'status'=>$r->status,
                'intent'=>is_array($intent) ? ($intent['label'] ?? '') : '',
                'date'=>JalaliDate::format($r->completed_at ?: $r->requested_at, 'relative'),
            ];
        }, $rows ?: []);
        wp_send_json_success(['rows'=>$data]);
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

        $meta = json_decode($a->ecommerce_data?:'null',true);
        $err = is_array($meta) ? ($meta['error']??'') : '';
        $dbg = is_array($meta) ? ($meta['debug']??'') : '';

        $intent = $this->classify_intent($comps);
        // Persist the intent so it survives, and sync to the page's target-keyword data
        if ($intent['dominant'] !== '' && empty($a->intent_data)) {
            $wpdb->update($wpdb->prefix.'viraseo_serp_analysis', ['intent_data'=>wp_json_encode($intent)], ['id'=>$id]);
            if ($a->post_id) {
                update_post_meta((int)$a->post_id, '_viraseo_serp_intent', [
                    'label'=>$intent['label'],
                    'dominant'=>$intent['dominant'],
                    'recommendation'=>$intent['recommendation'],
                    'avg_words'=>(int)$a->avg_word_count,
                    'keyword'=>$a->keyword,
                    'date'=>current_time('mysql'),
                ]);
            }
        }

        wp_send_json_success([
            'status'=>'completed',
            'keyword'=>$a->keyword,
            'avg_words'=>PersianText::format_number($a->avg_word_count),
            'avg_headings'=>$a->avg_headings,
            'lsi'=>json_decode($a->lsi_keywords?:'[]',true),
            'gap'=>json_decode($a->content_gap?:'[]',true),
            'questions'=>json_decode($a->questions?:'[]',true),
            'ecommerce'=>is_array($meta)?($meta['ecommerce']??null):null,
            'error'=>$err,
            'debug'=>$dbg,
            'intent'=>$intent,
            'saved_for_post'=> $a->post_id ? true : false,
            'competitors'=>array_map(fn($c)=>[
                'pos'=>$c->position,'url'=>$c->url,'domain'=>$c->domain,'title'=>$c->title,
                'words'=>$c->word_count,'h1'=>$c->h1_count,'h2'=>$c->h2_count,'h3'=>$c->h3_count,
                'images'=>$c->images_count,'schema'=>$c->schema_types,
                'snippet'=>$c->snippet ?? $c->meta_desc ?? '',
            ], $comps),
        ]);
    }

    /**
     * Classify search intent from the top-10 results (Persian-aware).
     * Looks at titles + URLs for product / informational(article) / service signals
     * and returns the distribution + the dominant intent.
     */
    private function classify_intent(array $comps): array {
        $signals = [
            'product' => ['خرید','قیمت','فروش','سفارش','تخفیف','ارزان','خرید اینترنتی','فروشگاه','محصول'],
            'article' => ['آموزش','راهنما','چیست','چگونه','بهترین','معرفی','لیست','ترفند','نحوه','روش','۱۰','مقاله','بررسی'],
            'service' => ['خدمات','خدمت','مشاوره','شرکت','طراحی','تعمیر','نصب','اجرا','سفارش طراحی'],
        ];
        $url_signals = [
            'product' => ['/product','/shop','/store','/cart','/buy','/products','woocommerce'],
            'article' => ['/blog','/article','/mag','/news','/post','/wiki','/category'],
            'service' => ['/service','/services','/khadamat'],
        ];
        $score = ['product'=>0, 'article'=>0, 'service'=>0];
        $n = 0;
        foreach ($comps as $c) {
            $n++;
            $title = ' ' . PersianText::normalize((string)$c->title) . ' ';
            $url = strtolower((string)$c->url);
            foreach ($signals as $type => $words) {
                foreach ($words as $w) if (mb_strpos($title, $w) !== false) { $score[$type] += 2; break; }
            }
            foreach ($url_signals as $type => $frags) {
                foreach ($frags as $f) if (strpos($url, $f) !== false) { $score[$type] += 3; break; }
            }
        }
        if ($n === 0) return ['dominant'=>'', 'label'=>'نامشخص', 'dist'=>$score, 'recommendation'=>''];

        arsort($score);
        $dominant = array_key_first($score);
        $total = array_sum($score) ?: 1;
        $labels = ['product'=>'محصول‌محور (خرید)', 'article'=>'مقاله‌محور (اطلاعاتی)', 'service'=>'خدماتی'];
        $recs = [
            'product' => 'نتایج برتر صفحات محصول/فروشگاهی هستند. برای رقابت، یک صفحه محصول یا دسته‌بندی با قیمت و دکمه خرید بسازید.',
            'article' => 'نتایج برتر مقاله‌ی آموزشی/اطلاعاتی هستند. یک مقاله‌ی جامع و عمیق (با H2/H3 و پاسخ به سوالات) بنویسید.',
            'service' => 'نتایج برتر صفحات خدماتی/شرکتی هستند. یک صفحه خدمات حرفه‌ای با نمونه‌کار و فرم تماس بسازید.',
        ];
        return [
            'dominant'=>$dominant,
            'label'=>$labels[$dominant] ?? 'نامشخص',
            'dist'=>['product'=>round($score['product']*100/$total), 'article'=>round($score['article']*100/$total), 'service'=>round($score['service']*100/$total)],
            'recommendation'=>$recs[$dominant] ?? '',
        ];
    }
}
