<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\{PersianText, JalaliDate};

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
        add_action('wp_ajax_viraseo_targets_list', [$this, 'ajax_targets_list']);
        add_action('wp_ajax_viraseo_target_save', [$this, 'ajax_target_save']);
    }

    /** List pages with their target keyword, source, GSC suggestion + stats (for the management page). */
    public function ajax_targets_list(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $gt = $wpdb->prefix . 'viraseo_gsc_keywords';
        $has_gsc = ($wpdb->get_var("SHOW TABLES LIKE '{$gt}'") === $gt);
        $search = sanitize_text_field($_POST['search'] ?? '');

        $args = ['post_type'=>self::TYPES, 'post_status'=>'publish', 'numberposts'=>200, 'orderby'=>'modified', 'order'=>'DESC'];
        if ($search) $args['s'] = $search;
        $posts = get_posts($args);
        $link_scores = get_option('viraseo_link_scores', []);
        if (!is_array($link_scores)) $link_scores = [];

        $rows = [];
        foreach ($posts as $p) {
            $own = (string) get_post_meta($p->ID, self::META, true);
            $rm = self::rank_math_keyword($p->ID);
            $current = $own !== '' ? PersianText::normalize($own) : $rm;
            $source = $own !== '' ? 'دستی (ViraSEO)' : ($rm !== '' ? 'Rank Math' : '—');

            $suggest = ''; $stats = null;
            if ($has_gsc) {
                $url = get_permalink($p->ID);
                $suggest = (string) $wpdb->get_var($wpdb->prepare(
                    "SELECT keyword FROM {$gt} WHERE page_url=%s ORDER BY clicks DESC, impressions DESC LIMIT 1", $url
                ));
                if ($current !== '') {
                    $s = $wpdb->get_row($wpdb->prepare(
                        "SELECT SUM(clicks) c, SUM(impressions) i, AVG(position) p FROM {$gt} WHERE page_url=%s AND keyword=%s", $url, $current
                    ));
                    if ($s && ($s->i !== null)) $stats = [
                        'clicks'=>PersianText::format_number((int)$s->c),
                        'impressions'=>PersianText::format_number((int)$s->i),
                        'position'=>JalaliDate::to_fa(number_format((float)$s->p, 1)),
                    ];
                }
            }

            $rows[] = [
                'id'=>$p->ID,
                'title'=>$p->post_title ?: '(بدون عنوان)',
                'type'=>$p->post_type,
                'edit'=>get_edit_post_link($p->ID, 'raw'),
                'current'=>$current,
                'source'=>$source,
                'suggest'=>$suggest,
                'stats'=>$stats,
                'link_score'=>(int)($link_scores[$p->ID] ?? 0),
                'serp_intent'=>(function($pid){
                    $si = get_post_meta($pid, '_viraseo_serp_intent', true);
                    if (!is_array($si) || empty($si['label'])) return null;
                    return ['label'=>$si['label'], 'rec'=>$si['recommendation'] ?? '', 'avg_words'=>$si['avg_words'] ?? 0];
                })($p->ID),
            ];
        }
        wp_send_json_success(['rows'=>$rows, 'has_gsc'=>$has_gsc]);
    }

    /** Save a target keyword for a single post from the management page. */
    public function ajax_target_save(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('دسترسی غیرمجاز.');
        $id = absint($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('شناسه نامعتبر.');
        $kw = PersianText::normalize(sanitize_text_field($_POST['keyword'] ?? ''));
        if ($kw === '') delete_post_meta($id, self::META);
        else update_post_meta($id, self::META, $kw);
        wp_send_json_success(['message'=>'ذخیره شد.']);
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
