<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Api\AiClient;
use ViraSEO\Utils\{JalaliDate, PersianText};

/**
 * Keyword Strategy & Plan [🟢 / ⚡ AI]
 * A keyword bank where researched keywords are selected, grouped into clusters/silos,
 * prioritized, and turned into content (draft posts) — optionally planned by AI.
 */
class KeywordStrategy {
    public function __construct() {
        add_action('wp_ajax_viraseo_plan_list', [$this, 'ajax_list']);
        add_action('wp_ajax_viraseo_plan_add', [$this, 'ajax_add']);
        add_action('wp_ajax_viraseo_plan_update', [$this, 'ajax_update']);
        add_action('wp_ajax_viraseo_plan_delete', [$this, 'ajax_delete']);
        add_action('wp_ajax_viraseo_plan_ai', [$this, 'ajax_ai_plan']);
        add_action('wp_ajax_viraseo_plan_draft', [$this, 'ajax_create_draft']);
    }

    private function table(): string { global $wpdb; return $wpdb->prefix . 'viraseo_keyword_plan'; }

    private function insert_keyword(string $kw, string $cluster = '', string $intent = '', int $priority = 0): bool {
        global $wpdb;
        $kw = PersianText::normalize($kw);
        if ($kw === '') return false;
        $hash = md5(mb_strtolower($kw));
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table()} WHERE keyword_hash=%s", $hash))) return false;
        return (bool) $wpdb->insert($this->table(), [
            'keyword'=>$kw, 'keyword_hash'=>$hash,
            'cluster'=>$cluster ?: null, 'intent'=>$intent ?: null,
            'priority'=>$priority, 'status'=>'planned',
        ]);
    }

    public function ajax_list(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table()} ORDER BY cluster IS NULL, cluster, priority DESC, id DESC");
        $st = ['planned'=>'برنامه‌ریزی‌شده','in_progress'=>'در حال تولید','done'=>'انجام‌شده'];
        $clusters = [];
        foreach ($rows as $r) {
            $c = $r->cluster ?: 'بدون خوشه';
            $clusters[$c][] = [
                'id'=>(int)$r->id, 'keyword'=>$r->keyword,
                'intent'=>$r->intent ?: '—', 'status'=>$r->status, 'status_fa'=>$st[$r->status] ?? $r->status,
                'priority'=>(int)$r->priority,
                'post'=> $r->post_id ? ['id'=>(int)$r->post_id, 'edit'=>get_edit_post_link((int)$r->post_id,'raw'), 'title'=>get_the_title((int)$r->post_id)] : null,
            ];
        }
        $out = [];
        foreach ($clusters as $name => $items) $out[] = ['cluster'=>$name, 'items'=>$items, 'count'=>count($items)];
        wp_send_json_success(['clusters'=>$out, 'total'=>count($rows)]);
    }

    public function ajax_add(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $raw = (string) wp_unslash($_POST['keywords'] ?? '');
        $cluster = sanitize_text_field($_POST['cluster'] ?? '');
        $intent = sanitize_text_field($_POST['intent'] ?? '');
        $added = 0; $skipped = 0;
        foreach (preg_split('/[\n,،]+/u', $raw) as $kw) {
            $kw = trim($kw);
            if ($kw === '') continue;
            if ($this->insert_keyword($kw, $cluster, $intent)) $added++; else $skipped++;
        }
        wp_send_json_success(['message'=>sprintf('✅ %d کلمه اضافه شد. (%d تکراری)', $added, $skipped)]);
    }

    public function ajax_update(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $field = sanitize_key($_POST['field'] ?? '');
        if (!$id || !in_array($field, ['status','cluster','intent','priority'], true)) wp_send_json_error('داده نامعتبر.');
        $val = $field === 'priority' ? absint($_POST['value'] ?? 0) : sanitize_text_field($_POST['value'] ?? '');
        $wpdb->update($this->table(), [$field=>$val], ['id'=>$id]);
        wp_send_json_success();
    }

    public function ajax_delete(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        if ($id) $wpdb->delete($this->table(), ['id'=>$id]);
        wp_send_json_success();
    }

    /** AI builds a full keyword plan (clusters/silos) from a seed and auto-adds it. */
    public function ajax_ai_plan(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');
        $seed = PersianText::normalize(sanitize_text_field($_POST['seed'] ?? ''));
        if ($seed === '') wp_send_json_error('موضوع را وارد کنید.');
        $biz = sanitize_text_field($_POST['business'] ?? '');

        $system = 'شما استراتژیست سئوی فارسی هستید. فقط و فقط یک شیء JSON معتبر برگردان، بدون هیچ توضیح اضافه.';
        $user = "برای موضوع «{$seed}»" . ($biz ? " (کسب‌وکار: {$biz})" : '') . " یک استراتژی کلمات کلیدی و ساختار خوشه/سیلو برای بازار فارسی ایران بساز.\n"
              . "خروجی فقط JSON با این ساختار:\n"
              . '{"clusters":[{"name":"نام خوشه","pillar":"عنوان صفحه ستون","keywords":[{"kw":"کلمه","intent":"informational|commercial|transactional"}]}]}' . "\n"
              . "حداکثر ۶ خوشه و در هر خوشه ۴ تا ۸ کلمه.";
        $res = AiClient::chat($system, $user, 0.4);
        if (isset($res['error'])) wp_send_json_error($res['error']);

        // Extract the JSON object from the AI response
        $text = $res['text'];
        $json = null;
        if (preg_match('/\{.*\}/s', $text, $m)) $json = json_decode($m[0], true);
        if (!is_array($json) || empty($json['clusters'])) {
            wp_send_json_error('پاسخ AI قابل پردازش نبود. متن خام: ' . mb_substr($text, 0, 300));
        }
        $added = 0;
        foreach ($json['clusters'] as $cl) {
            $cname = sanitize_text_field($cl['name'] ?? '');
            foreach (($cl['keywords'] ?? []) as $kwi) {
                $kw = is_array($kwi) ? ($kwi['kw'] ?? '') : (string)$kwi;
                $intent = is_array($kwi) ? ($kwi['intent'] ?? '') : '';
                if ($this->insert_keyword($kw, $cname, $intent)) $added++;
            }
        }
        wp_send_json_success(['message'=>sprintf('✅ %d کلمه در %d خوشه به برنامه اضافه شد.', $added, count($json['clusters'])), 'cost'=>$res['cost']]);
    }

    /** Create a draft post for a planned keyword (optionally with an AI outline). */
    public function ajax_create_draft(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id=%d", $id));
        if (!$row) wp_send_json_error('کلمه یافت نشد.');

        $content = '';
        if (!empty($_POST['with_ai']) && AiClient::is_enabled()) {
            $res = AiClient::chat(
                'شما نویسنده‌ی سئوی فارسی هستید. خروجی را به‌صورت HTML با تگ‌های h2/h3/p بده.',
                "برای کلمه هدف «{$row->keyword}» یک ساختار اولیه‌ی مقاله بده: یک پاراگراف مقدمه و چند زیرعنوان H2/H3 با یک جمله توضیح زیر هرکدام. مطابق Helpful Content گوگل.",
                0.6
            );
            if (!isset($res['error'])) $content = $res['text'];
        }

        $post_id = wp_insert_post([
            'post_title'   => $row->keyword,
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => 'post',
        ]);
        if (is_wp_error($post_id) || !$post_id) wp_send_json_error('ساخت پیش‌نویس ناموفق بود.');

        update_post_meta($post_id, '_viraseo_target_keyword', $row->keyword);
        $wpdb->update($this->table(), ['status'=>'in_progress', 'post_id'=>$post_id], ['id'=>$id]);
        wp_send_json_success(['message'=>'✅ پیش‌نویس ساخته شد.', 'edit'=>get_edit_post_link($post_id, 'raw')]);
    }
}
