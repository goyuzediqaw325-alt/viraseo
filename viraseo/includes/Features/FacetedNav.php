<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

/** Feature 6: Faceted Navigation Crawl Budget [🟢 مستقل] */
class FacetedNav {
    private const OPT = 'viraseo_faceted';

    public function __construct() {
        if (!class_exists('WooCommerce')) return;
        add_action('wp_head', [$this, 'noindex'], 1);
        add_action('template_redirect', [$this, 'header'], 1);
        add_action('wp_ajax_viraseo_faceted_get', [$this, 'ajax_get']);
        add_action('wp_ajax_viraseo_faceted_save', [$this, 'ajax_save']);
    }

    private function cfg(): array {
        return wp_parse_args(get_option(self::OPT,[]),[
            'enabled'=>true,'max_params'=>1,
            'filters'=>['min_price','max_price','orderby','rating_filter'],
            'safe'=>['product_cat','product_tag','paged','s'],
            'prefix'=>'pa_','noindex_sort'=>true,
        ]);
    }

    private function should(): bool {
        $c = $this->cfg();
        if (!$c['enabled']) return false;
        if (!is_shop() && !is_product_taxonomy()) return false;
        $count = 0;
        foreach ($_GET as $k=>$v) {
            if ($v==='' || in_array($k,$c['safe'],true)) continue;
            if (in_array($k,$c['filters'],true) || strpos($k,$c['prefix'])===0) $count++;
            if ($k==='orderby' && $c['noindex_sort']) $count++;
        }
        return $count > (int)$c['max_params'];
    }

    public function noindex(): void {
        if ($this->should()) echo '<meta name="robots" content="noindex,nofollow"/>'."\n";
    }

    public function header(): void {
        if ($this->should() && !headers_sent()) header('X-Robots-Tag: noindex, nofollow');
    }

    public function ajax_get(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        $c = $this->cfg();
        $c['filters_text'] = implode("\n",$c['filters']);
        $c['safe_text'] = implode("\n",$c['safe']);
        wp_send_json_success($c);
    }

    public function ajax_save(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        update_option(self::OPT, [
            'enabled'=>!empty($_POST['enabled']),
            'max_params'=>max(0,absint($_POST['max_params']??1)),
            'filters'=>array_filter(array_map('trim',explode("\n",$_POST['filters_text']??''))),
            'safe'=>array_filter(array_map('trim',explode("\n",$_POST['safe_text']??''))),
            'prefix'=>sanitize_text_field($_POST['prefix']??'pa_'),
            'noindex_sort'=>!empty($_POST['noindex_sort']),
        ]);
        wp_send_json_success(['message'=>'ذخیره شد.']);
    }
}
