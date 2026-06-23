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
        add_action('http_api_curl', ['\ViraSEO\Api\AiClient', 'apply_curl_proxy'], 10, 1);
        add_action('wp_ajax_viraseo_ai_models', [$this, 'ajax_models']);
        add_action('wp_ajax_viraseo_ai_serp_strategy', [$this, 'ajax_serp_strategy']);
        add_action('wp_ajax_viraseo_ai_content', [$this, 'ajax_content']);
        add_action('wp_ajax_viraseo_ai_cannibal', [$this, 'ajax_cannibal']);
        add_action('wp_ajax_viraseo_ai_faq', [$this, 'ajax_faq']);
        add_action('wp_ajax_viraseo_ai_keywords', [$this, 'ajax_keywords']);
        add_action('wp_ajax_viraseo_ai_review', [$this, 'ajax_review']);
        add_action('wp_ajax_viraseo_ai_save', [$this, 'ajax_save']);
        add_action('wp_ajax_viraseo_ai_saved', [$this, 'ajax_saved']);
        add_action('wp_ajax_viraseo_ai_saved_delete', [$this, 'ajax_saved_delete']);
    }

    /** Save an AI output for later reference. */
    public function ajax_save(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        if (trim($content) === '') wp_send_json_error('محتوای خالی.');
        $title = sanitize_text_field($_POST['title'] ?? '') ?: mb_substr(wp_strip_all_tags($content), 0, 60);
        $wpdb->insert($wpdb->prefix.'viraseo_ai_outputs', [
            'kind'=>sanitize_text_field($_POST['kind'] ?? 'general'),
            'title'=>$title,
            'content'=>$content,
            'post_id'=>absint($_POST['post_id'] ?? 0) ?: null,
        ]);
        wp_send_json_success(['message'=>'✅ ذخیره شد.', 'id'=>$wpdb->insert_id]);
    }

    public function ajax_saved(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $kinds = ['general'=>'عمومی','serp'=>'استراتژی SERP','keywords'=>'تحقیق کلمات','review'=>'بازبینی محتوا','faq'=>'FAQ','content'=>'طرح محتوا','cluster'=>'خوشه','cannibal'=>'کنیبالایزیشن'];
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_ai_outputs ORDER BY id DESC LIMIT 100");
        $data = array_map(fn($r)=>[
            'id'=>(int)$r->id, 'title'=>$r->title, 'content'=>$r->content,
            'kind'=>$kinds[$r->kind] ?? $r->kind,
            'date'=>\ViraSEO\Utils\JalaliDate::format($r->created_at, 'datetime'),
        ], $rows ?: []);
        wp_send_json_success(['rows'=>$data]);
    }

    public function ajax_saved_delete(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        if ($id) $wpdb->delete($wpdb->prefix.'viraseo_ai_outputs', ['id'=>$id]);
        wp_send_json_success();
    }

    /** Shared guard + run helper. */
    private function run(string $system, string $user, float $temp = 0.5): void {
        if (!AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');
        $res = AiClient::chat($system, $user, $temp);
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success(['text'=>$res['text'], 'cost'=>$res['cost'], 'tokens'=>$res['tokens']]);
    }

    private const SEO_PERSONA = 'شما متخصص ارشد سئوی فارسی هستید و اصول Helpful Content و E-E-A-T گوگل را کامل می‌شناسید. فقط فارسی، دقیق و ساختارمند با تیتر پاسخ بده.';

    /** AI plan to resolve keyword cannibalization between two pages. */
    public function ajax_cannibal(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $kw = sanitize_text_field($_POST['keyword'] ?? '');
        $u1 = esc_url_raw($_POST['url1'] ?? ''); $u2 = esc_url_raw($_POST['url2'] ?? '');
        $p1 = sanitize_text_field($_POST['pos1'] ?? '-'); $p2 = sanitize_text_field($_POST['pos2'] ?? '-');
        if (!$kw || !$u1 || !$u2) wp_send_json_error('داده ناقص.');
        $user = "تعارض کلمه‌ای (Cannibalization) برای کلمه «{$kw}»:\n"
              . "صفحه ۱: {$u1} (جایگاه {$p1})\nصفحه ۲: {$u2} (جایگاه {$p2})\n\n"
              . "تحلیل کن و دقیق بگو: کدام صفحه باید صفحه‌ی اصلی بماند؟ آیا ادغام (merge) بهتر است یا ریدایرکت ۳۰۱ یا متمایزسازی محتوا؟ "
              . "گام‌به‌گام بگو چه کنم، شامل پیشنهاد لینک داخلی و کانونیکال.";
        $this->run(self::SEO_PERSONA, $user, 0.4);
    }

    /** AI generates FAQ Q&A + valid FAQPage JSON-LD for a page. */
    public function ajax_faq(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $pid = absint($_POST['post_id'] ?? 0);
        $kw = PersianText::normalize(sanitize_text_field($_POST['keyword'] ?? ''));
        $ctx = '';
        if ($pid) {
            $post = get_post($pid);
            if ($post) {
                if ($kw === '') $kw = TargetKeywords::get($pid);
                $ctx = "عنوان: {$post->post_title}\nخلاصه محتوا: " . mb_substr(wp_strip_all_tags($post->post_content), 0, 1200) . "\n";
            }
        }
        if ($kw === '' && $ctx === '') wp_send_json_error('کلمه هدف یا صفحه مشخص نیست.');
        $user = "{$ctx}کلمه هدف: «{$kw}»\n\n"
              . "۶ تا ۸ سوال متداول واقعی کاربران فارسی درباره این موضوع بنویس با پاسخ‌های کوتاه و دقیق. "
              . "سپس یک بلوک کامل و معتبر <script type=\"application/ld+json\"> با اسکیمای FAQPage بده که آماده‌ی کپی در صفحه باشد.";
        $this->run(self::SEO_PERSONA, $user, 0.5);
    }

    /** AI keyword research from a seed topic — grouped by intent. */
    public function ajax_keywords(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $seed = PersianText::normalize(sanitize_text_field($_POST['seed'] ?? ''));
        if ($seed === '') wp_send_json_error('موضوع یا کلمه‌ی دانه را وارد کنید.');
        $biz = sanitize_text_field($_POST['business'] ?? '');
        $user = "موضوع/کلمه‌ی اصلی: «{$seed}»" . ($biz ? "\nزمینه‌ی کسب‌وکار: {$biz}" : '') . "\n\n"
              . "یک تحقیق کلمات کلیدی کامل برای بازار فارسی‌زبان ایران انجام بده:\n"
              . "۱) کلمات کلیدی اطلاعاتی (Informational) با نوع محتوای پیشنهادی (مقاله/راهنما)\n"
              . "۲) کلمات تجاری/خرید (Commercial/Transactional) مناسب صفحه محصول یا خدمات\n"
              . "۳) کلمات دم‌بلند (Long-tail) کم‌رقابت\n"
              . "۴) سوالات پرتکرار کاربران (برای FAQ و زیرعنوان)\n"
              . "۵) پیشنهاد خوشه‌بندی موضوعی (Topic Clusters) و صفحه‌ی ستون.\n"
              . "هر بخش را با لیست مرتب ارائه بده.";
        $this->run(self::SEO_PERSONA, $user, 0.6);
    }

    /** AI content review/audit of a page (quality, E-E-A-T, helpful content, gaps). */
    public function ajax_review(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $pid = absint($_POST['post_id'] ?? 0);
        if (!$pid) wp_send_json_error('صفحه‌ای انتخاب نشده.');
        $post = get_post($pid);
        if (!$post) wp_send_json_error('صفحه یافت نشد.');
        $kw = TargetKeywords::get($pid);
        $content = mb_substr(wp_strip_all_tags($post->post_content), 0, 4000);
        $user = "کلمه هدف: «{$kw}»\nعنوان: {$post->post_title}\n\nمحتوای صفحه:\n{$content}\n\n"
              . "این محتوا را بازبینی و تحلیل کن و گزارش بده:\n"
              . "۱) امتیاز کیفیت کلی (۰ تا ۱۰۰) و دلیل\n"
              . "۲) رعایت اصول Helpful Content و E-E-A-T (تجربه، تخصص، اعتبار، اعتماد)\n"
              . "۳) نقاط ضعف و بخش‌های ناقص که باید اضافه شوند\n"
              . "۴) مشکلات خوانایی و ساختار\n"
              . "۵) فهرست اقدامات مشخص برای بهبود رتبه این صفحه.";
        $this->run(self::SEO_PERSONA, $user, 0.4);
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
