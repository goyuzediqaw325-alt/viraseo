<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\JalaliDate;

/** Feature 5: OOS Traffic Protector [🟢 مستقل - WooCommerce] */
class OOSProtector {
    public function __construct() {
        if (!class_exists('WooCommerce')) return;
        add_action('template_redirect', [$this, 'redirect'], 5);
        add_action('woocommerce_before_single_product', [$this, 'alternatives'], 15);
        add_action('woocommerce_product_set_stock_status', [$this, 'on_change'], 10, 3);
        add_action('wp_ajax_viraseo_get_oos', [$this, 'ajax_list']);
    }

    public function redirect(): void {
        if (!is_product()) return;
        global $post;
        $p = wc_get_product($post->ID);
        if (!$p || $p->get_stock_status()!=='outofstock') return;
        if (get_post_meta($post->ID,'_viraseo_discontinued',true)!=='yes') return;
        if ($this->has_traffic($post->ID)) return;
        $url = $this->get_redirect_url($post->ID, $p);
        if ($url) { wp_redirect($url, 301); exit; }
    }

    public function alternatives(): void {
        global $post;
        $p = wc_get_product($post->ID);
        if (!$p || $p->get_stock_status()!=='outofstock') return;
        if (!$this->has_traffic($post->ID)) return;
        $alts = wc_get_products(['status'=>'publish','stock_status'=>'instock','category'=>$p->get_category_ids(),'exclude'=>[$post->ID],'limit'=>4,'orderby'=>'popularity']);
        if ($alts) include VIRASEO_DIR.'templates/partials/oos-alternatives.php';
    }

    public function on_change(int $pid, string $status, $product): void {
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_oos_log';
        if ($status==='outofstock') {
            $wpdb->replace($t, ['product_id'=>$pid,'title'=>is_object($product)?$product->get_name():get_the_title($pid),'has_traffic'=>$this->has_traffic($pid)?1:0,'action_taken'=>'pending']);
        } elseif ($status==='instock') {
            $wpdb->delete($t, ['product_id'=>$pid]);
        }
    }

    public function ajax_list(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_oos_log ORDER BY detected_at DESC LIMIT 50");
        $data = array_map(fn($r)=>['id'=>$r->product_id,'title'=>$r->title,'traffic'=>(bool)$r->has_traffic,'action'=>$r->action_taken,'date'=>JalaliDate::format($r->detected_at,'relative')], $rows);
        wp_send_json_success(['rows'=>$data]);
    }

    private function has_traffic(int $pid): bool {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(impressions),0) FROM {$wpdb->prefix}viraseo_gsc_keywords WHERE post_id=%d AND date_recorded>=DATE_SUB(NOW(),INTERVAL 30 DAY)",$pid)) >= 5;
    }

    private function get_redirect_url(int $pid, $p): string {
        $custom = get_post_meta($pid,'_viraseo_redirect_url',true);
        if ($custom) return $custom;
        $terms = get_the_terms($pid,'product_cat');
        if ($terms && !is_wp_error($terms)) return get_term_link($terms[0]);
        return wc_get_page_permalink('shop');
    }
}
