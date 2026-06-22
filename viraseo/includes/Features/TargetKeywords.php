<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\PersianText;

/**
 * Target Keyword system: lets ViraSEO know which keyword each page targets.
 * Priority: ViraSEO field (_viraseo_target_keyword) → Rank Math focus keyword → top content keyword.
 * Powers smarter internal linking (correct anchors) and topical clustering.
 */
class TargetKeywords {
    const META = '_viraseo_target_keyword';
    const TYPES = ['post', 'page', 'product'];

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_box']);
        add_action('save_post', [$this, 'save'], 10, 2);
        add_action('wp_ajax_viraseo_suggest_targets_gsc', [$this, 'ajax_suggest_from_gsc']);
    }

    public function add_box(): void {
        foreach (self::TYPES as $t) {
            add_meta_box('viraseo_target_kw', 'ViraSEO — کلمه هدف', [$this, 'render'], $t, 'side', 'high');
        }
    }

    public function render(\WP_Post $post): void {
        wp_nonce_field('viraseo_target_kw', 'viraseo_target_kw_nonce');
        $val = get_post_meta($post->ID, self::META, true);
        $rm = self::rank_math_keyword($post->ID);
        echo '<p style="direction:rtl"><label for="viraseo_tk"><strong>کلمه کلیدی هدف این صفحه:</strong></label></p>';
        echo '<input type="text" id="viraseo_tk" name="viraseo_target_keyword" value="' . esc_attr($val) . '" style="width:100%;direction:rtl" placeholder="مثلا: طراحی سایت در تبریز">';
        if ($rm) {
            echo '<p style="direction:rtl;font-size:11px;color:#666;margin-top:6px">کلمه کانونی Rank Math: <code>' . esc_html($rm) . '</code>';
            echo ' — اگر این فیلد را خالی بگذارید، از کلمه Rank Math استفاده می‌شود.</p>';
        } else {
            echo '<p style="direction:rtl;font-size:11px;color:#666;margin-top:6px">این کلمه برای لینک‌سازی هوشمند و خوشه‌بندی موضوعی استفاده می‌شود.</p>';
        }
    }

    public function save(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['viraseo_target_kw_nonce']) || !wp_verify_nonce($_POST['viraseo_target_kw_nonce'], 'viraseo_target_kw')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (!in_array($post->post_type, self::TYPES, true)) return;
        $kw = isset($_POST['viraseo_target_keyword']) ? PersianText::normalize(sanitize_text_field($_POST['viraseo_target_keyword'])) : '';
        if ($kw === '') delete_post_meta($post_id, self::META);
        else update_post_meta($post_id, self::META, $kw);
    }

    /** First Rank Math focus keyword (comma-separated → take the first). */
    public static function rank_math_keyword(int $post_id): string {
        $raw = (string) get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if ($raw === '') return '';
        $parts = explode(',', $raw);
        return PersianText::normalize(trim($parts[0]));
    }

    /** Resolved target keyword: ViraSEO → Rank Math → ''. */
    public static function get(int $post_id): string {
        $own = (string) get_post_meta($post_id, self::META, true);
        if ($own !== '') return PersianText::normalize($own);
        return self::rank_math_keyword($post_id);
    }

    /**
     * Automation: for pages that have NO target keyword (neither ViraSEO nor Rank Math),
     * assign their best-performing Search Console query (most clicks) as the target keyword.
     */
    public function ajax_suggest_from_gsc(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $gt = $wpdb->prefix . 'viraseo_gsc_keywords';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$gt}'") !== $gt) wp_send_json_error('ابتدا داده‌های سرچ کنسول را همگام‌سازی کنید.');

        $posts = get_posts(['post_type'=>self::TYPES, 'post_status'=>'publish', 'numberposts'=>500, 'fields'=>'ids']);
        $applied = 0; $skipped = 0;
        foreach ($posts as $pid) {
            if (self::get((int)$pid) !== '') { $skipped++; continue; } // keep existing
            $url = get_permalink($pid);
            $kw = $wpdb->get_var($wpdb->prepare(
                "SELECT keyword FROM {$gt} WHERE page_url=%s ORDER BY clicks DESC, impressions DESC LIMIT 1", $url
            ));
            if (!$kw) {
                // try without scheme/trailing-slash variance
                $kw = $wpdb->get_var($wpdb->prepare(
                    "SELECT keyword FROM {$gt} WHERE page_url LIKE %s ORDER BY clicks DESC, impressions DESC LIMIT 1",
                    '%' . $wpdb->esc_like(rtrim(preg_replace('#^https?://[^/]+#', '', $url), '/')) . '%'
                ));
            }
            if ($kw) { update_post_meta($pid, self::META, PersianText::normalize($kw)); $applied++; }
        }
        wp_send_json_success(['message'=>sprintf('✅ %d صفحه کلمه هدف از سرچ کنسول گرفت. (%d صفحه از قبل کلمه داشت)', $applied, $skipped)]);
    }
}
