<?php
/**
 * Feature 9: Zero-Cost Farsi Keyword Discovery
 *
 * Architecture:
 * - n8n hits Google Autocomplete API (free, no auth needed) for Farsi suggestions
 * - n8n scrapes "Related Searches" (جستجوهای مرتبط) from SERP bottom
 * - Combined results sent to WP as "Keyword Ideas" JSON
 * - WP stores ideas in custom table, displays in Persian UI
 * - User can select ideas → generate Draft Post with content brief
 *
 * Data Flow:
 * 1. User enters seed keyword in WP admin
 * 2. WP sends webhook to n8n with seed keyword
 * 3. n8n fetches: Google Suggest API + Related Searches from SERP
 * 4. n8n normalizes Persian text (ZWNJ), deduplicates, categorizes
 * 5. n8n POSTs keyword ideas back to WP REST endpoint
 * 6. WP stores in apseo_keyword_ideas table
 * 7. User selects keywords → "Generate Draft" creates wp_posts draft
 *
 * @package AdvancedPersianSEO\Features
 */

namespace APSEO\Features;

defined('ABSPATH') || exit;

use APSEO\Admin\Dashboard;
use APSEO\Api\WebhookHandler;
use APSEO\Utils\JalaliDate;
use APSEO\Utils\PersianText;

class KeywordDiscovery {

    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers
        add_action('wp_ajax_apseo_discover_keywords', [$this, 'ajax_start_discovery']);
        add_action('wp_ajax_apseo_get_keyword_ideas', [$this, 'ajax_get_ideas']);
        add_action('wp_ajax_apseo_generate_content_brief', [$this, 'ajax_generate_brief']);
        add_action('wp_ajax_apseo_dismiss_keyword_idea', [$this, 'ajax_dismiss_idea']);
        add_action('wp_ajax_apseo_get_discovery_history', [$this, 'ajax_get_history']);
    }

    /**
     * AJAX: Start keyword discovery (send to n8n)
     */
    public function ajax_start_discovery(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        $seed_keyword = sanitize_text_field($_POST['seed_keyword'] ?? '');

        if (empty($seed_keyword) || mb_strlen($seed_keyword) < 2) {
            wp_send_json_error(__('لطفاً یک کلمه کلیدی حداقل ۲ کاراکتری وارد کنید.', 'advanced-persian-seo'));
        }

        // Normalize the seed keyword
        $seed_keyword = PersianText::normalize($seed_keyword);

        // Check for recent discovery (within 6 hours)
        $recent = $this->get_recent_discovery($seed_keyword);
        if ($recent) {
            wp_send_json_success([
                'status'       => 'already_exists',
                'discovery_id' => $recent->id,
                'message'      => __('کشف کلمات اخیری برای این عبارت موجود است.', 'advanced-persian-seo'),
            ]);
            return;
        }

        // Send to n8n
        $result = $this->send_discovery_request($seed_keyword);

        if ($result['success']) {
            wp_send_json_success([
                'status'       => 'processing',
                'discovery_id' => $result['discovery_id'],
                'message'      => __('جستجوی کلمات کلیدی شروع شد. لطفاً چند ثانیه صبر کنید...', 'advanced-persian-seo'),
            ]);
        } else {
            wp_send_json_error(
                sprintf(__('خطا: %s', 'advanced-persian-seo'), $result['error'] ?? '')
            );
        }
    }

    /**
     * Send discovery request to n8n
     */
    private function send_discovery_request(string $seed_keyword): array {
        global $wpdb;

        $settings = Dashboard::get_settings();
        $webhook_url = $settings['n8n_webhook_base_url'] . '/webhook/apseo-keyword-discover';

        if (empty($settings['n8n_webhook_base_url'])) {
            return ['success' => false, 'error' => 'n8n not configured'];
        }

        // Create discovery record
        $table = $wpdb->prefix . 'apseo_keyword_ideas';
        $discovery_id = md5($seed_keyword . time());

        // Store a placeholder record to track the request
        $wpdb->insert($wpdb->prefix . 'apseo_keyword_discoveries', [
            'discovery_id'  => $discovery_id,
            'seed_keyword'  => $seed_keyword,
            'status'        => 'processing',
            'requested_by'  => get_current_user_id(),
            'requested_at'  => current_time('mysql'),
        ]);

        // Send to n8n
        $response = wp_remote_post($webhook_url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'   => 'application/json',
                'X-APSEO-Secret' => $settings['n8n_secret_key'],
            ],
            'body' => wp_json_encode([
                'action'        => 'keyword_discover',
                'seed_keyword'  => $seed_keyword,
                'discovery_id'  => $discovery_id,
                'callback_url'  => rest_url('apseo/v1/keyword-ideas'),
                'site_url'      => get_site_url(),
                'language'      => 'fa',
            ]),
        ]);

        if (is_wp_error($response)) {
            $wpdb->update($wpdb->prefix . 'apseo_keyword_discoveries', [
                'status' => 'failed',
            ], ['discovery_id' => $discovery_id]);

            return ['success' => false, 'error' => $response->get_error_message()];
        }

        return ['success' => true, 'discovery_id' => $discovery_id];
    }

    /**
     * Check for recent discovery of same keyword
     */
    private function get_recent_discovery(string $seed_keyword): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'apseo_keyword_discoveries';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE seed_keyword = %s
               AND status = 'completed'
               AND requested_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)
             ORDER BY requested_at DESC LIMIT 1",
            $seed_keyword
        ));
    }

    /**
     * AJAX: Get keyword ideas for a discovery
     */
    public function ajax_get_ideas(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'apseo_keyword_ideas';
        $disc_table = $wpdb->prefix . 'apseo_keyword_discoveries';

        $discovery_id = sanitize_text_field($_POST['discovery_id'] ?? '');
        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = 30;
        $offset = ($page - 1) * $per_page;
        $source_filter = sanitize_text_field($_POST['source'] ?? '');
        $status_filter = sanitize_text_field($_POST['status'] ?? 'active');

        // Check discovery status
        $discovery = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$disc_table} WHERE discovery_id = %s",
            $discovery_id
        ));

        if (!$discovery) {
            wp_send_json_error(__('شناسه کشف نامعتبر.', 'advanced-persian-seo'));
        }

        if ($discovery->status === 'processing') {
            wp_send_json_success([
                'status'  => 'processing',
                'message' => __('n8n در حال جمع‌آوری کلمات است...', 'advanced-persian-seo'),
            ]);
            return;
        }

        // Get ideas
        $where = "WHERE discovery_id = %s";
        $params = [$discovery_id];

        if ($status_filter === 'active') {
            $where .= " AND status != 'dismissed'";
        } elseif ($status_filter === 'dismissed') {
            $where .= " AND status = 'dismissed'";
        } elseif ($status_filter === 'used') {
            $where .= " AND status = 'used'";
        }

        if (!empty($source_filter)) {
            $where .= " AND source = %s";
            $params[] = $source_filter;
        }

        $params[] = $per_page;
        $params[] = $offset;

        $ideas = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY relevance_score DESC LIMIT %d OFFSET %d",
            ...$params
        ));

        $count_params = array_slice($params, 0, -2);
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} {$where}",
            ...$count_params
        ));

        $source_labels = [
            'autocomplete'    => __('پیشنهاد گوگل (Autocomplete)', 'advanced-persian-seo'),
            'related_search'  => __('جستجوهای مرتبط', 'advanced-persian-seo'),
            'people_also_ask' => __('سؤالات مرتبط (PAA)', 'advanced-persian-seo'),
        ];

        $formatted = array_map(function ($idea) use ($source_labels) {
            return [
                'id'              => $idea->id,
                'keyword'         => $idea->keyword,
                'source'          => $idea->source,
                'source_label'    => $source_labels[$idea->source] ?? $idea->source,
                'relevance_score' => $idea->relevance_score,
                'search_volume_hint' => $idea->search_volume_hint ?: __('نامشخص', 'advanced-persian-seo'),
                'word_count'      => mb_substr_count($idea->keyword, ' ') + 1,
                'is_question'     => $idea->is_question ? true : false,
                'status'          => $idea->status,
                'created_at'      => JalaliDate::format($idea->created_at, 'relative'),
            ];
        }, $ideas);

        // Summary stats
        $summary = [
            'seed_keyword'     => $discovery->seed_keyword,
            'total_ideas'      => PersianText::format_number($total),
            'autocomplete'     => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE discovery_id = %s AND source = 'autocomplete'",
                $discovery_id
            )),
            'related_searches' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE discovery_id = %s AND source = 'related_search'",
                $discovery_id
            )),
            'questions'        => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE discovery_id = %s AND is_question = 1",
                $discovery_id
            )),
        ];

        wp_send_json_success([
            'status'  => 'completed',
            'ideas'   => $formatted,
            'total'   => (int) $total,
            'pages'   => ceil($total / $per_page),
            'summary' => $summary,
        ]);
    }

    /**
     * AJAX: Generate a Draft Post from selected keyword ideas
     *
     * Creates a wp_posts draft with:
     * - Title: Primary keyword
     * - Content: Structured content brief with H2s from related keywords
     * - Meta: Stores all selected keywords for reference
     */
    public function ajax_generate_content_brief(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'apseo_keyword_ideas';

        $selected_ids = $_POST['selected_ids'] ?? [];
        $primary_keyword = sanitize_text_field($_POST['primary_keyword'] ?? '');
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'post');

        if (empty($selected_ids) || !is_array($selected_ids)) {
            wp_send_json_error(__('لطفاً حداقل یک کلمه کلیدی انتخاب کنید.', 'advanced-persian-seo'));
        }

        // Sanitize IDs
        $selected_ids = array_map('absint', $selected_ids);
        $ids_placeholder = implode(',', $selected_ids);

        // Get selected keywords
        $keywords = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE id IN ({$ids_placeholder}) ORDER BY relevance_score DESC"
        );

        if (empty($keywords)) {
            wp_send_json_error(__('کلمات انتخاب‌شده یافت نشدند.', 'advanced-persian-seo'));
        }

        // Use first keyword as primary if not specified
        if (empty($primary_keyword)) {
            $primary_keyword = $keywords[0]->keyword;
        }

        // Build content brief
        $content = $this->build_content_brief($primary_keyword, $keywords);

        // Create draft post
        $post_data = [
            'post_title'   => $primary_keyword,
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => in_array($post_type, ['post', 'page', 'product']) ? $post_type : 'post',
            'post_author'  => get_current_user_id(),
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            wp_send_json_error(
                sprintf(__('خطا در ایجاد پیش‌نویس: %s', 'advanced-persian-seo'), $post_id->get_error_message())
            );
        }

        // Store keyword brief metadata
        $keyword_list = array_map(fn($kw) => $kw->keyword, $keywords);
        update_post_meta($post_id, '_apseo_content_brief_keywords', $keyword_list);
        update_post_meta($post_id, '_apseo_primary_keyword', $primary_keyword);
        update_post_meta($post_id, '_apseo_brief_generated_at', current_time('mysql'));

        // Mark keywords as "used"
        $wpdb->query(
            "UPDATE {$table} SET status = 'used' WHERE id IN ({$ids_placeholder})"
        );

        wp_send_json_success([
            'message'  => sprintf(
                __('پیش‌نویس «%s» با موفقیت ایجاد شد.', 'advanced-persian-seo'),
                $primary_keyword
            ),
            'post_id'  => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
        ]);
    }

    /**
     * Build structured content brief from keywords
     */
    private function build_content_brief(string $primary_keyword, array $keywords): string {
        $questions = [];
        $subtopics = [];
        $long_tails = [];

        foreach ($keywords as $kw) {
            if ($kw->is_question) {
                $questions[] = $kw->keyword;
            } elseif (mb_substr_count($kw->keyword, ' ') >= 3) {
                $long_tails[] = $kw->keyword;
            } else {
                $subtopics[] = $kw->keyword;
            }
        }

        $content = '';

        // Introduction section
        $content .= "<!-- بریف محتوا - تولید شده توسط سئو پیشرفته فارسی -->\n";
        $content .= "<!-- کلمه کلیدی اصلی: {$primary_keyword} -->\n\n";

        $content .= "<h2>مقدمه: {$primary_keyword}</h2>\n";
        $content .= "<p>[در این بخش مقدمه‌ای جامع درباره «{$primary_keyword}» بنویسید. حدود ۱۵۰-۲۰۰ کلمه.]</p>\n\n";

        // Subtopic sections as H2s
        if (!empty($subtopics)) {
            $content .= "<!-- زیرموضوعات پیشنهادی (بر اساس کلمات مرتبط) -->\n";
            foreach (array_slice($subtopics, 0, 6) as $topic) {
                $content .= "<h2>{$topic}</h2>\n";
                $content .= "<p>[محتوای مرتبط با «{$topic}» را در این بخش بنویسید. حدود ۲۰۰-۳۰۰ کلمه.]</p>\n\n";
            }
        }

        // Long-tail keywords as subsections
        if (!empty($long_tails)) {
            $content .= "<!-- بخش‌های تکمیلی (کلمات Long-tail) -->\n";
            foreach (array_slice($long_tails, 0, 4) as $lt) {
                $content .= "<h3>{$lt}</h3>\n";
                $content .= "<p>[توضیحات مختصر درباره «{$lt}».]</p>\n\n";
            }
        }

        // FAQ section from questions
        if (!empty($questions)) {
            $content .= "<h2>سؤالات متداول</h2>\n";
            foreach (array_slice($questions, 0, 8) as $q) {
                $content .= "<h3>{$q}</h3>\n";
                $content .= "<p>[پاسخ کوتاه و مفید به سؤال بالا.]</p>\n\n";
            }
        }

        // Conclusion
        $content .= "<h2>جمع‌بندی</h2>\n";
        $content .= "<p>[نتیجه‌گیری نهایی درباره «{$primary_keyword}» و ارائه توصیه به خواننده.]</p>\n\n";

        // Hidden keyword reference
        $all_keywords = array_map(fn($kw) => $kw->keyword, $keywords);
        $content .= "<!-- کلمات کلیدی هدف:\n" . implode("\n", $all_keywords) . "\n-->\n";

        return $content;
    }

    /**
     * AJAX: Dismiss a keyword idea
     */
    public function ajax_dismiss_idea(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'apseo_keyword_ideas';
        $id = absint($_POST['idea_id'] ?? 0);

        if (!$id) {
            wp_send_json_error(__('شناسه نامعتبر.', 'advanced-persian-seo'));
        }

        $wpdb->update($table, ['status' => 'dismissed'], ['id' => $id]);
        wp_send_json_success(['message' => __('رد شد.', 'advanced-persian-seo')]);
    }

    /**
     * AJAX: Get discovery history
     */
    public function ajax_get_history(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'apseo_keyword_discoveries';
        $ideas_table = $wpdb->prefix . 'apseo_keyword_ideas';

        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        $discoveries = $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, 
                    (SELECT COUNT(*) FROM {$ideas_table} WHERE discovery_id = d.discovery_id) as idea_count
             FROM {$table} d
             ORDER BY d.requested_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        $status_labels = [
            'processing' => __('در حال پردازش', 'advanced-persian-seo'),
            'completed'  => __('تکمیل شده', 'advanced-persian-seo'),
            'failed'     => __('ناموفق', 'advanced-persian-seo'),
        ];

        $formatted = array_map(function ($d) use ($status_labels) {
            return [
                'discovery_id'  => $d->discovery_id,
                'seed_keyword'  => $d->seed_keyword,
                'status'        => $d->status,
                'status_label'  => $status_labels[$d->status] ?? $d->status,
                'idea_count'    => PersianText::format_number($d->idea_count),
                'requested_at'  => JalaliDate::format($d->requested_at, 'relative'),
            ];
        }, $discoveries);

        wp_send_json_success([
            'discoveries' => $formatted,
            'total'       => (int) $total,
            'pages'       => ceil($total / $per_page),
        ]);
    }
}
