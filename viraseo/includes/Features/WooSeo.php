<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\PersianText;

/**
 * Store-focused SEO [🟢 مستقل - WooCommerce]
 * Treats product category pages as silo PILLARS (mother/target pages):
 *  - Category SEO audit (description, product count, GSC performance).
 *  - Auto-link products UP to their category (silo structure).
 *  - Per-category target keyword (term meta) + strategy checklist.
 */
class WooSeo {
    const TERM_META = '_viraseo_target_keyword';

    public function __construct() {
        add_action('wp_ajax_viraseo_woo_categories', [$this, 'ajax_categories']);
        add_action('wp_ajax_viraseo_woo_autolink', [$this, 'ajax_autolink']);
        add_action('wp_ajax_viraseo_woo_cat_kw', [$this, 'ajax_cat_keyword']);
    }

    private function gsc_impressions(string $url): int {
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_gsc_keywords';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) return 0;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(impressions),0) FROM {$t} WHERE page_url=%s", $url));
    }

    public function ajax_categories(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!class_exists('WooCommerce')) wp_send_json_error('ووکامرس فعال نیست.');

        $cats = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);
        if (is_wp_error($cats) || !$cats) wp_send_json_error('دسته‌بندی محصولی یافت نشد.');

        $rows = [];
        foreach ($cats as $c) {
            $url = get_term_link($c);
            if (is_wp_error($url)) continue;
            $descLen = mb_strlen(trim(wp_strip_all_tags($c->description)));
            $kw = (string) get_term_meta($c->term_id, self::TERM_META, true);
            $issues = [];
            if ($descLen < 100) $issues[] = 'توضیحات دسته کوتاه/خالی است (برای سئو حداقل ۳۰۰ کلمه توصیه می‌شود)';
            if ($c->count < 3) $issues[] = 'محصولات کم — دسته نازک';
            if ($kw === '') $issues[] = 'کلمه هدف دسته تعیین نشده';
            $rows[] = [
                'id'=>$c->term_id,
                'name'=>$c->name,
                'url'=>$url,
                'count'=>(int)$c->count,
                'count_fa'=>PersianText::format_number((int)$c->count),
                'desc_len'=>$descLen,
                'desc_words'=>PersianText::format_number(PersianText::word_count(wp_strip_all_tags($c->description))),
                'keyword'=>$kw,
                'impressions'=>PersianText::format_number($this->gsc_impressions($url)),
                'issues'=>$issues,
                'health'=> empty($issues) ? 'ok' : (count($issues) >= 2 ? 'bad' : 'warn'),
            ];
        }
        usort($rows, fn($a,$b)=>$b['count'] <=> $a['count']);
        wp_send_json_success(['rows'=>$rows]);
    }

    /** Save a per-category target keyword (term meta). */
    public function ajax_cat_keyword(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $id = absint($_POST['id'] ?? 0);
        $kw = PersianText::normalize(sanitize_text_field($_POST['keyword'] ?? ''));
        if (!$id) wp_send_json_error('شناسه نامعتبر.');
        if ($kw === '') delete_term_meta($id, self::TERM_META);
        else update_term_meta($id, self::TERM_META, $kw);
        wp_send_json_success(['message'=>'کلمه هدف دسته ذخیره شد.']);
    }

    /**
     * Silo automation: ensure each product in a category links UP to its category page.
     * Inserts a contextual link into the product description if missing.
     */
    public function ajax_autolink(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('edit_products') && !current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!class_exists('WooCommerce')) wp_send_json_error('ووکامرس فعال نیست.');

        $cat_id = absint($_POST['id'] ?? 0);
        if (!$cat_id) wp_send_json_error('دسته نامعتبر.');
        $term = get_term($cat_id, 'product_cat');
        if (!$term || is_wp_error($term)) wp_send_json_error('دسته یافت نشد.');
        $cat_url = get_term_link($term);
        $cat_kw = (string) get_term_meta($cat_id, self::TERM_META, true) ?: $term->name;

        $products = wc_get_products(['category'=>[$term->slug], 'limit'=>100, 'status'=>'publish']);
        $linked = 0; $skipped = 0;
        foreach ($products as $product) {
            $pid = $product->get_id();
            $content = $product->get_description();
            if ($content === '' ) { $skipped++; continue; }
            if (strpos($content, 'href="'.$cat_url.'"') !== false) { $skipped++; continue; }
            // Append a contextual silo link (safe — no risky inline replacement on shop content)
            $anchor = $cat_kw;
            $content .= "\n\n<p>مشاهده‌ی همه محصولات <a href=\"" . esc_url($cat_url) . "\">" . esc_html($anchor) . "</a>.</p>";
            wp_update_post(['ID'=>$pid, 'post_content'=>$content]);
            $linked++;
        }
        wp_send_json_success(['message'=>sprintf('✅ %d محصول به صفحه دسته «%s» لینک شد. (%d مورد از قبل لینک داشت)', $linked, $term->name, $skipped)]);
    }
}
