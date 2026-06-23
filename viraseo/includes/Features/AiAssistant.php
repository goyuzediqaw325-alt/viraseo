<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Api\AiClient;
use ViraSEO\Utils\PersianText;

/**
 * AI Assistant [⚡ اختیاری - OpenRouter]
 * When enabled, supercharges analyses: competitor-beating strategy, content briefs,
 * and create/edit guidance aligned with Google's Helpful Content principles.
 */
class AiAssistant {
    public function __construct() {
        add_action('wp_ajax_viraseo_ai_models', [$this, 'ajax_models']);
        add_action('wp_ajax_viraseo_ai_serp_strategy', [$this, 'ajax_serp_strategy']);
        add_action('wp_ajax_viraseo_ai_content', [$this, 'ajax_content']);
    }

    public function ajax_models(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $res = AiClient::models(!empty($_POST['force']));
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success(['models' => $res['models'], 'current' => AiClient::model()]);
    }

    /** AI competitor-beating strategy + content brief from a SERP analysis. */
    public function ajax_serp_strategy(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');
        global $wpdb;
        $id = absint($_POST['analysis_id'] ?? 0);
        $a = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}viraseo_serp_analysis WHERE id=%d", $id));
        if (!$a) wp_send_json_error('تحلیل یافت نشد.');

        $comps = $wpdb->get_results($wpdb->prepare(
            "SELECT title, domain, word_count, h1_count, h2_count, h3_count FROM {$wpdb->prefix}viraseo_serp_competitors WHERE analysis_id=%d ORDER BY position LIMIT 10", $id
        ));
        $lsi = implode('، ', array_slice((array)json_decode($a->lsi_keywords ?: '[]', true), 0, 20));
        $gap = implode('، ', array_slice((array)json_decode($a->content_gap ?: '[]', true), 0, 15));
        $questions = implode("\n- ", array_slice((array)json_decode($a->questions ?: '[]', true), 0, 10));

        $compLines = '';
        foreach ($comps as $i => $c) {
            $compLines .= ($i+1) . ". {$c->domain} — عنوان: {$c->title} — کلمات: {$c->word_count} — هدینگ‌ها H1/H2/H3: {$c->h1_count}/{$c->h2_count}/{$c->h3_count}\n";
        }

        $system = 'شما یک متخصص ارشد سئوی فارسی و استراتژیست محتوا هستید که اصول Helpful Content گوگل را کامل می‌شناسید. '
                . 'پاسخ را فقط به زبان فارسی روان و کاملاً ساختارمند با تیترها بده.';
        $user = "کلمه کلیدی هدف: «{$a->keyword}»\n\n"
              . "میانگین کلمات رقبا: {$a->avg_word_count} | میانگین هدینگ: {$a->avg_headings}\n\n"
              . "۱۰ نتیجه‌ی برتر گوگل:\n{$compLines}\n"
              . "کلمات LSI مرتبط: {$lsi}\n"
              . "شکاف محتوایی: {$gap}\n"
              . "سوالات کاربران (PAA):\n- {$questions}\n\n"
              . "یک «نقشه‌ی شکست رقبا» و طرح نگارش (Content Brief) کامل بده که شامل این بخش‌ها باشد:\n"
              . "۱) هدف کاربر و نوع صفحه‌ی پیشنهادی\n"
              . "۲) ساختار پیشنهادی هدینگ‌ها (H1 و H2/H3) برای پوشش کامل موضوع\n"
              . "۳) تعداد کلمات هدف و نکات عمق محتوا\n"
              . "۴) کلمات و موضوعاتی که رقبا جا انداخته‌اند (مزیت رقابتی ما)\n"
              . "۵) سوالاتی که حتماً باید پاسخ داده شوند (FAQ)\n"
              . "۶) چک‌لیست Helpful Content گوگل برای این صفحه (تجربه، تخصص، اعتبار).";

        $res = AiClient::chat($system, $user, 0.5);
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success(['text' => $res['text'], 'cost' => $res['cost'], 'tokens' => $res['tokens']]);
    }

    /** AI create/edit guidance for a specific page + target keyword. */
    public function ajax_content(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');

        $pid = absint($_POST['post_id'] ?? 0);
        $keyword = PersianText::normalize(sanitize_text_field($_POST['keyword'] ?? ''));
        $mode = sanitize_text_field($_POST['mode'] ?? 'outline'); // outline | improve

        $context = '';
        if ($pid) {
            $post = get_post($pid);
            if ($post) {
                if ($keyword === '') $keyword = TargetKeywords::get($pid);
                $excerpt = mb_substr(wp_strip_all_tags($post->post_content), 0, 1500);
                $context = "عنوان فعلی صفحه: {$post->post_title}\nبخشی از محتوای فعلی:\n{$excerpt}\n\n";
            }
        }
        if ($keyword === '') wp_send_json_error('کلمه هدف مشخص نیست.');

        $system = 'شما نویسنده و ویراستار ارشد سئوی فارسی هستید و بر اصول Helpful Content و E-E-A-T گوگل مسلط‌اید. '
                . 'فقط فارسی و ساختارمند پاسخ بده.';
        if ($mode === 'improve') {
            $user = "{$context}کلمه هدف: «{$keyword}»\n\nاین صفحه را برای تحول رتبه بازنویسی/بهبود بده: نقاط ضعف فعلی، "
                  . "ساختار جدید هدینگ‌ها، بخش‌هایی که باید اضافه شوند، و نکات Helpful Content برای افزایش اعتبار و تجربه‌ی کاربر.";
        } else {
            $user = "{$context}کلمه هدف: «{$keyword}»\n\nیک طرح کامل نگارش مقاله/صفحه بده: عنوان جذاب، متادیسکریپشن، "
                  . "ساختار کامل هدینگ‌ها (H1/H2/H3)، نکات کلیدی هر بخش، کلمات مرتبط، و یک بخش سوالات متداول. مطابق Helpful Content گوگل.";
        }

        $res = AiClient::chat($system, $user, 0.6);
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success(['text' => $res['text'], 'cost' => $res['cost'], 'tokens' => $res['tokens']]);
    }
}
