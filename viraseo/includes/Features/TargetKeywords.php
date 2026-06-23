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
    const META_SECONDARY = '_viraseo_target_keywords_secondary';
    const TYPES = ['post', 'page', 'product'];

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_box']);
        add_action('save_post', [$this, 'save'], 10, 2);
        add_action('wp_ajax_viraseo_suggest_targets_gsc', [$this, 'ajax_suggest_from_gsc']);
        add_action('wp_ajax_viraseo_targets_list', [$this, 'ajax_targets_list']);
        add_action('wp_ajax_viraseo_target_save', [$this, 'ajax_target_save']);
    }

    /** All public post types except attachment (so big/Woo sites show every page type). */
    public static function public_types(): array {
        $types = get_post_types(['public' => true], 'names');
        unset($types['attachment']);
        return array_values($types);
    }

    /** True if the page is marked noindex (Rank Math robots meta). */
    public static function is_noindex(int $post_id): bool {
        $robots = get_post_meta($post_id, 'rank_math_robots', true);
        return is_array($robots) && in_array('noindex', $robots, true);
    }

    /** Functional/low-value pages to skip from analysis (cart, checkout, account, etc.). */
    public static function excluded_ids(): array {
        static $ids = null;
        if ($ids !== null) return $ids;
        $ids = [];
        if (function_exists('wc_get_page_id')) {
            foreach (['cart','checkout','myaccount','terms','view_order'] as $p) {
                $id = (int) wc_get_page_id($p);
                if ($id > 0) $ids[] = $id;
            }
        }
        $pp = (int) get_option('wp_page_for_privacy_policy');
        if ($pp) $ids[] = $pp;
        $ids = array_values(array_unique(array_filter($ids)));
        return $ids;
    }

    /** Should this page be excluded from SEO analysis (noindex or functional page)? */
    public static function is_excluded(int $post_id): bool {
        if (self::is_noindex($post_id)) return true;
        return in_array($post_id, self::excluded_ids(), true);
    }

    /** Secondary target keywords: ViraSEO secondary meta + Rank Math additional focus keywords. */
    public static function get_secondary(int $post_id): array {
        $out = [];
        $own = (string) get_post_meta($post_id, self::META_SECONDARY, true);
        if ($own !== '') {
            foreach (preg_split('/[,،\n]+/u', $own) as $k) {
                $k = PersianText::normalize(trim($k));
                if ($k !== '') $out[] = $k;
            }
        }
        // Rank Math allows up to 5 focus keywords (comma-separated); 2nd+ are secondary
        $rm = (string) get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if ($rm !== '') {
            $parts = array_slice(explode(',', $rm), 1);
            foreach ($parts as $k) {
                $k = PersianText::normalize(trim($k));
                if ($k !== '') $out[] = $k;
            }
        }
        return array_values(array_unique(array_filter($out)));
    }

    /** All target keywords for a page: primary first, then secondary (unique). */
    public static function get_all(int $post_id): array {
        $all = [];
        $primary = self::get($post_id);
        if ($primary !== '') $all[] = $primary;
        foreach (self::get_secondary($post_id) as $s) $all[] = $s;
        return array_values(array_unique(array_filter($all)));
    }

    /** Map of post_id => total GSC impressions. */
    private function gsc_impr_map(): array {
        global $wpdb;
        $gt = $wpdb->prefix . 'viraseo_gsc_keywords';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$gt}'") !== $gt) return [];
        $map = [];
        foreach ($wpdb->get_results("SELECT page_url, SUM(impressions) i FROM {$gt} GROUP BY page_url") as $r) {
            $pid = url_to_postid($r->page_url);
            if ($pid) $map[$pid] = (int)$r->i;
        }
        return $map;
    }

    /** List pages with target keyword, source, GSC stats — all post types, filterable, sortable, paginated. */
    public function ajax_targets_list(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $gt = $wpdb->prefix . 'viraseo_gsc_keywords';
        $has_gsc = ($wpdb->get_var("SHOW TABLES LIKE '{$gt}'") === $gt);

        $search = sanitize_text_field($_POST['search'] ?? '');
        $type_filter = sanitize_text_field($_POST['post_type'] ?? '');
        $orderby = in_array($_POST['orderby'] ?? '', ['modified','link_score','impressions','title'], true) ? $_POST['orderby'] : 'modified';
        $order = (strtolower($_POST['order'] ?? 'desc') === 'asc') ? 'asc' : 'desc';
        $page = max(1, absint($_POST['page'] ?? 1));
        $per = 50;

        $types = ($type_filter && $type_filter !== 'all') ? [$type_filter] : self::public_types();
        $q = ['post_type'=>$types, 'post_status'=>'publish', 'numberposts'=>3000, 'fields'=>'ids', 'orderby'=>'modified', 'order'=>'DESC'];
        if ($search) $q['s'] = $search;
        $ids = get_posts($q);

        $link_scores = get_option('viraseo_link_scores', []);
        if (!is_array($link_scores)) $link_scores = [];
        $impr_map = $has_gsc ? $this->gsc_impr_map() : [];

        // Lightweight candidates (skip noindex), then sort by the chosen factor
        $cand = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if (self::is_excluded($id)) continue;
            $cand[] = [
                'id'=>$id,
                'ls'=>(int)($link_scores[$id] ?? 0),
                'impr'=>(int)($impr_map[$id] ?? 0),
                'title'=>get_the_title($id),
                'modified'=>(int)get_post_modified_time('U', true, $id),
            ];
        }
        usort($cand, function($a, $b) use ($orderby, $order) {
            $v = match($orderby) {
                'link_score'  => $a['ls'] <=> $b['ls'],
                'impressions' => $a['impr'] <=> $b['impr'],
                'title'       => strcmp($a['title'], $b['title']),
                default       => $a['modified'] <=> $b['modified'],
            };
            return $order === 'asc' ? $v : -$v;
        });

        $total = count($cand);
        $slice = array_slice($cand, ($page - 1) * $per, $per);

        $rows = [];
        foreach ($slice as $c) {
            $pid = $c['id'];
            $own = (string) get_post_meta($pid, self::META, true);
            $rm = self::rank_math_keyword($pid);
            $current = $own !== '' ? PersianText::normalize($own) : $rm;
            $source = $own !== '' ? 'دستی (ViraSEO)' : ($rm !== '' ? 'Rank Math' : '—');

            $suggest = ''; $stats = null;
            if ($has_gsc) {
                $url = get_permalink($pid);
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
            $ptype = get_post_type_object(get_post_type($pid));
            $si = get_post_meta($pid, '_viraseo_serp_intent', true);
            $rows[] = [
                'id'=>$pid,
                'title'=>$c['title'] ?: '(بدون عنوان)',
                'type'=> $ptype ? $ptype->labels->singular_name : get_post_type($pid),
                'edit'=>get_edit_post_link($pid, 'raw'),
                'current'=>$current,
                'secondary'=>self::get_secondary($pid),
                'source'=>$source,
                'suggest'=>$suggest,
                'stats'=>$stats,
                'link_score'=>$c['ls'],
                'serp_intent'=> is_array($si) && !empty($si['label']) ? ['label'=>$si['label'], 'rec'=>$si['recommendation'] ?? '', 'avg_words'=>$si['avg_words'] ?? 0] : null,
            ];
        }

        $type_objs = [];
        foreach (self::public_types() as $t) {
            $o = get_post_type_object($t);
            $type_objs[] = ['slug'=>$t, 'label'=>$o ? $o->labels->name : $t];
        }

        wp_send_json_success([
            'rows'=>$rows, 'total'=>$total, 'pages'=>(int)ceil($total / $per), 'page'=>$page,
            'types'=>$type_objs, 'has_gsc'=>$has_gsc,
        ]);
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
        $sec = sanitize_textarea_field($_POST['secondary'] ?? '');
        if (trim($sec) === '') delete_post_meta($id, self::META_SECONDARY);
        else update_post_meta($id, self::META_SECONDARY, $sec);
        wp_send_json_success(['message'=>'ذخیره شد.']);
    }

    public function add_box(): void {
        foreach (self::public_types() as $t) {
            add_meta_box('viraseo_target_kw', 'ViraSEO — کلمه هدف', [$this, 'render'], $t, 'side', 'high');
        }
    }

    public function render(\WP_Post $post): void {
        wp_nonce_field('viraseo_target_kw', 'viraseo_target_kw_nonce');
        $val = get_post_meta($post->ID, self::META, true);
        $sec = get_post_meta($post->ID, self::META_SECONDARY, true);
        $rm = self::rank_math_keyword($post->ID);
        echo '<p style="direction:rtl"><label for="viraseo_tk"><strong>کلمه کلیدی هدف اصلی:</strong></label></p>';
        echo '<input type="text" id="viraseo_tk" name="viraseo_target_keyword" value="' . esc_attr($val) . '" style="width:100%;direction:rtl" placeholder="مثلا: طراحی سایت در تبریز">';
        echo '<p style="direction:rtl;margin-top:8px"><label for="viraseo_tk_sec"><strong>کلمات کلیدی فرعی:</strong> (با کاما جدا کنید)</label></p>';
        echo '<textarea id="viraseo_tk_sec" name="viraseo_target_keywords_secondary" rows="2" style="width:100%;direction:rtl" placeholder="مثلا: قیمت طراحی سایت، طراحی سایت ارزان">' . esc_textarea($sec) . '</textarea>';
        if ($rm) {
            echo '<p style="direction:rtl;font-size:11px;color:#666;margin-top:6px">کلمه کانونی Rank Math: <code>' . esc_html($rm) . '</code> — اگر کلمه اصلی را خالی بگذارید، از Rank Math استفاده می‌شود.</p>';
        } else {
            echo '<p style="direction:rtl;font-size:11px;color:#666;margin-top:6px">این کلمات برای لینک‌سازی هوشمند و خوشه‌بندی موضوعی استفاده می‌شوند.</p>';
        }
    }

    public function save(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['viraseo_target_kw_nonce']) || !wp_verify_nonce($_POST['viraseo_target_kw_nonce'], 'viraseo_target_kw')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (!in_array($post->post_type, self::public_types(), true)) return;
        $kw = isset($_POST['viraseo_target_keyword']) ? PersianText::normalize(sanitize_text_field($_POST['viraseo_target_keyword'])) : '';
        if ($kw === '') delete_post_meta($post_id, self::META);
        else update_post_meta($post_id, self::META, $kw);
        $sec = isset($_POST['viraseo_target_keywords_secondary']) ? sanitize_textarea_field($_POST['viraseo_target_keywords_secondary']) : '';
        if (trim($sec) === '') delete_post_meta($post_id, self::META_SECONDARY);
        else update_post_meta($post_id, self::META_SECONDARY, $sec);
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

        $posts = get_posts(['post_type'=>self::public_types(), 'post_status'=>'publish', 'numberposts'=>1000, 'fields'=>'ids']);
        $applied = 0; $skipped = 0;
        foreach ($posts as $pid) {
            if (self::is_excluded((int)$pid)) { $skipped++; continue; }
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
