<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\PersianText;

/** Feature 3: Internal Links + Orphan Pages + Suggestions [🟢 مستقل] */
class InternalSilo {
    private ?array $cached_health = null;

    public function __construct() {
        add_action('viraseo_scan_orphan_pages', [$this, 'scan']);
        add_action('viraseo_generate_link_suggestions', [$this, 'suggest']);
        add_action('wp_ajax_viraseo_trigger_scan', [$this, 'ajax_scan']);
        add_action('wp_ajax_viraseo_get_orphans', [$this, 'ajax_orphans']);
        add_action('wp_ajax_viraseo_get_suggestions', [$this, 'ajax_suggestions']);
        add_action('wp_ajax_viraseo_accept_link', [$this, 'ajax_accept']);
        add_action('wp_ajax_viraseo_reject_link', [$this, 'ajax_reject']);
        add_action('wp_ajax_viraseo_apply_link', [$this, 'ajax_apply']);
        add_action('wp_ajax_viraseo_link_clusters', [$this, 'ajax_clusters']);
        add_action('wp_ajax_viraseo_apply_all_links', [$this, 'ajax_apply_all']);
        add_action('wp_ajax_viraseo_link_graph', [$this, 'ajax_link_graph']);
        add_action('wp_ajax_viraseo_link_scores', [$this, 'ajax_link_scores']);
        add_action('wp_ajax_viraseo_cluster_link', [$this, 'ajax_cluster_link']);
        add_action('wp_ajax_viraseo_broken_links', [$this, 'ajax_broken_links']);
        add_action('wp_ajax_viraseo_ai_cluster', [$this, 'ajax_ai_cluster']);
        add_action('wp_ajax_viraseo_ai_suggestions', [$this, 'ajax_ai_suggestions']);
        add_action('wp_ajax_viraseo_cluster_content_single', [$this, 'ajax_cluster_content_single']);
        add_action('wp_ajax_viraseo_cluster_content_apply', [$this, 'ajax_cluster_content_apply']);
        add_action('wp_ajax_viraseo_cluster_content_generate', [$this, 'ajax_cluster_content_generate']);
        add_action('wp_ajax_viraseo_link_health', [$this, 'ajax_link_health']);
        add_action('wp_ajax_viraseo_link_health_history', [$this, 'ajax_link_health_history']);
    }

    /** AI content generation for a single cluster member page. */
    public function ajax_cluster_content_single(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!\ViraSEO\Api\AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');

        $keyword      = sanitize_text_field($_POST['keyword'] ?? '');
        $page_title   = sanitize_text_field($_POST['page_title'] ?? '');
        $page_url     = esc_url_raw($_POST['page_url'] ?? '');
        $pillar_title = sanitize_text_field($_POST['pillar_title'] ?? '');
        $pillar_url   = esc_url_raw($_POST['pillar_url'] ?? '');
        $cluster_pages = json_decode(wp_unslash($_POST['cluster_pages'] ?? '[]'), true);

        if (!$keyword || !$page_title) wp_send_json_error('داده ناقص: کلمه کلیدی و عنوان صفحه الزامی است.');
        if (!is_array($cluster_pages)) $cluster_pages = [];

        // Build the list of other cluster pages for internal linking instructions
        $links_list = '';
        $idx = 1;
        foreach (array_slice($cluster_pages, 0, 20) as $pg) {
            if (!is_array($pg)) continue;
            $t = $pg['title'] ?? ''; $u = $pg['url'] ?? '';
            if ($t === $page_title && $u === $page_url) continue;
            $links_list .= $idx . ". {$t} - {$u}\n";
            $idx++;
        }
        if ($pillar_title && $pillar_url && $pillar_title !== $page_title) {
            $links_list .= $idx . ". {$pillar_title} (صفحه ستون) - {$pillar_url}\n";
        }

        $system = 'شما نویسنده محتوای سئو فارسی هستید. وظیفه شما تولید محتوای کامل، حرفه‌ای و بهینه‌شده برای موتورهای جستجو است. '
            . 'خروجی شما باید HTML خالص باشد (بدون markdown). تمام محتوا فارسی باشد.';

        $user = "موضوع کلی خوشه: «{$keyword}»\n"
            . "عنوان صفحه‌ای که برایش محتوا تولید می‌کنی: «{$page_title}»\n"
            . "آدرس صفحه: {$page_url}\n\n"
            . "صفحات دیگر این خوشه (لینک داخلی به آنها بده):\n{$links_list}\n\n"
            . "لطفا موارد زیر را تولید کن:\n\n"
            . "1. **عنوان سئو** (۶۰ تا ۷۰ کاراکتر، شامل کلمه کلیدی اصلی) - در یک خط با پیشوند TITLE:\n"
            . "2. **توضیحات متا** (۱۵۰ تا ۱۶۰ کاراکتر، جذاب و شامل CTA) - در یک خط با پیشوند META:\n"
            . "3. **محتوای کامل HTML** (۱۵۰۰ تا ۲۵۰۰ کلمه) با پیشوند CONTENT:\n\n"
            . "قوانین محتوا:\n"
            . "- ساختار با H2 و H3 مناسب\n"
            . "- شامل بخش سوالات متداول (FAQ) با حداقل ۳ سوال\n"
            . "- لینک‌های داخلی طبیعی به صفحات خوشه با استفاده از عنوان آنها به عنوان انکرتکست (تگ <a href=\"url\">عنوان</a>)\n"
            . "- حداقل ۳ لینک داخلی به صفحات لیست‌شده بالا\n"
            . "- یک لینک به صفحه ستون (Pillar) با انکرتکست مناسب\n"
            . "- پاراگراف‌های کوتاه و خوانا\n"
            . "- استفاده از لیست‌های شماره‌دار و بولت‌دار\n"
            . "- محتوای جامع و عمیق درباره موضوع صفحه در چارچوب خوشه\n"
            . "- فارسی روان و حرفه‌ای";

        $res = \ViraSEO\Api\AiClient::chat($system, $user, 0.7, 4000);
        if (isset($res['error'])) wp_send_json_error($res['error']);

        $text = $res['text'] ?? '';

        // Parse the structured response
        $title = '';
        $meta_desc = '';
        $content = '';

        // Extract TITLE:
        if (preg_match('/TITLE:\s*(.+)/u', $text, $m)) {
            $title = trim(strip_tags($m[1]));
        }
        // Extract META:
        if (preg_match('/META:\s*(.+)/u', $text, $m)) {
            $meta_desc = trim(strip_tags($m[1]));
        }
        // Extract CONTENT: (everything after CONTENT: marker)
        if (preg_match('/CONTENT:\s*(.+)/su', $text, $m)) {
            $content = trim($m[1]);
        } else {
            // Fallback: if no CONTENT marker, take everything after META line
            $parts = preg_split('/META:.+\n/u', $text, 2);
            if (isset($parts[1])) $content = trim($parts[1]);
            else $content = $text;
        }

        // Clean the HTML content
        $content = \ViraSEO\Api\AiClient::clean_html($content);

        // Fallback title/meta if not parsed
        if (!$title) $title = $page_title;
        if (!$meta_desc) $meta_desc = mb_substr(wp_strip_all_tags($content), 0, 160);

        wp_send_json_success([
            'title'    => $title,
            'meta_desc'=> $meta_desc,
            'content'  => $content,
            'cost'     => $res['cost'] ?? 0,
            'tokens'   => $res['tokens'] ?? 0,
        ]);
    }

    /** Apply AI-generated content to an existing post (with backup). */
    public function ajax_cluster_content_apply(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $post_id  = absint($_POST['post_id'] ?? 0);
        $title    = sanitize_text_field($_POST['title'] ?? '');
        $meta_desc= sanitize_text_field($_POST['meta_desc'] ?? '');
        $content  = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        $keyword  = sanitize_text_field($_POST['keyword'] ?? '');

        if (!$post_id) wp_send_json_error('شناسه پست نامعتبر.');
        $post = get_post($post_id);
        if (!$post) wp_send_json_error('پست یافت نشد.');

        // Backup existing content
        update_post_meta($post_id, '_viraseo_content_backup', $post->post_content);
        update_post_meta($post_id, '_viraseo_title_backup', $post->post_title);

        // Update the post
        $update_data = ['ID' => $post_id, 'post_content' => $content];
        if ($title) $update_data['post_title'] = $title;
        $result = wp_update_post($update_data, true);
        if (is_wp_error($result)) wp_send_json_error('خطا در به‌روزرسانی: ' . $result->get_error_message());

        // Set target keyword if provided and not already set
        if ($keyword) {
            $existing_kw = get_post_meta($post_id, '_viraseo_target_keyword', true);
            if (!$existing_kw) update_post_meta($post_id, '_viraseo_target_keyword', $keyword);
        }

        // Set meta description - try popular SEO plugins first, fallback to own meta
        if ($meta_desc) {
            if (function_exists('update_post_meta')) {
                // Yoast SEO
                if (defined('WPSEO_VERSION')) {
                    update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_desc);
                }
                // Rank Math
                elseif (defined('RANK_MATH_VERSION')) {
                    update_post_meta($post_id, 'rank_math_description', $meta_desc);
                }
                // All in One SEO
                elseif (defined('AIOSEO_VERSION')) {
                    update_post_meta($post_id, '_aioseo_description', $meta_desc);
                }
                // Fallback: ViraSEO own meta
                update_post_meta($post_id, '_viraseo_meta_desc', $meta_desc);
            }
        }

        wp_send_json_success(['message' => '✅ محتوا با موفقیت ذخیره شد و نسخه پشتیبان ایجاد گردید.']);
    }

    /** Batch content generation for cluster members (summary mode). */
    public function ajax_cluster_content_generate(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!\ViraSEO\Api\AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');

        $keyword     = sanitize_text_field($_POST['keyword'] ?? '');
        $pages       = json_decode(wp_unslash($_POST['pages'] ?? '[]'), true);
        $pillar_id   = sanitize_text_field($_POST['pillar_id'] ?? '');
        $pillar_title= sanitize_text_field($_POST['pillar_title'] ?? '');
        $pillar_url  = esc_url_raw($_POST['pillar_url'] ?? '');

        if (!$keyword || !is_array($pages) || !$pages) wp_send_json_error('داده ناقص.');

        $generated = 0;
        $skipped = 0;
        $total_cost = 0;

        foreach ($pages as $pg) {
            if (!is_array($pg)) continue;
            $id = $pg['id'] ?? '';
            // Only process posts (IDs starting with 'p')
            if (strpos($id, 'p') !== 0 || strpos($id, 'pc') === 0) { $skipped++; continue; }
            $post_id = (int) substr($id, 1);
            if ($post_id < 1) { $skipped++; continue; }
            $generated++;
        }

        wp_send_json_success([
            'message' => sprintf('✅ %d صفحه قابل تولید محتوا شناسایی شد. (%d مورد رد شد — فقط نوشته‌ها قابل ویرایش‌اند)', $generated, $skipped),
            'eligible' => $generated,
            'skipped' => $skipped,
        ]);
    }

    /** AI review of pending link suggestions: prioritize, improve anchors, flag over-optimization. */
    public function ajax_ai_suggestions(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!\ViraSEO\Api\AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_link_suggestions WHERE status='pending' ORDER BY FIELD(match_type,'exact','partial','semantic'), score DESC LIMIT 40");
        if (!$rows) wp_send_json_error('پیشنهادی برای تحلیل نیست. ابتدا «اسکن لینک‌ها» را بزنید.');
        $list = '';
        foreach ($rows as $i => $r) {
            $list .= ($i+1).". از «".get_the_title($r->source_id)."» → به «".get_the_title($r->target_id)."» | انکر: {$r->anchor} | نوع: {$r->match_type} | امتیاز: {$r->score}\n";
        }
        $system = 'شما متخصص لینک‌سازی داخلی فارسی و معماری سئو هستید. فقط فارسی و ساختارمند پاسخ بده.';
        $user = "این پیشنهادهای لینک داخلی را بررسی کن:\n{$list}\n"
              . "۱) کدام پیشنهادها بیشترین ارزش سئو را دارند و باید اول اعمال شوند؟\n"
              . "۲) برای کدام لینک‌ها انکرتکست بهتری پیشنهاد می‌دهی (با تنوع انکر برای جلوگیری از Over-Optimization)؟\n"
              . "۳) کدام پیشنهادها را رد کنیم (کم‌ربط یا تکراری)؟\n"
              . "۴) آیا لینک مهمی جا افتاده که باید اضافه شود؟";
        $res = \ViraSEO\Api\AiClient::chat($system, $user, 0.4);
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success(['text'=>$res['text'], 'cost'=>$res['cost'], 'tokens'=>$res['tokens']]);
    }

    /** Detect internal links pointing to non-published posts or 404 URLs. */
    public function ajax_broken_links(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $host = wp_parse_url(get_site_url(), PHP_URL_HOST);
        $posts = $wpdb->get_results("SELECT ID,post_title,post_content FROM {$wpdb->posts} WHERE post_status='publish' AND post_content LIKE '%<a %' LIMIT 800");

        $broken = []; $headChecks = 0; $headCache = [];
        foreach ($posts as $p) {
            if (!preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $p->post_content, $m, PREG_SET_ORDER)) continue;
            foreach ($m as $match) {
                $href = trim($match[1]); $anchor = wp_strip_all_tags($match[2]);
                if ($href === '' || $href[0] === '#' || stripos($href, 'mailto:') === 0 || stripos($href, 'tel:') === 0 || stripos($href, 'javascript:') === 0) continue;
                $abs = (strpos($href, '/') === 0) ? get_site_url() . $href : $href;
                $h = wp_parse_url($abs, PHP_URL_HOST);
                if ($h && $h !== $host) continue; // internal only
                $reason = '';
                $tid = url_to_postid($abs);
                if ($tid) {
                    $st = get_post_status($tid);
                    if ($st !== 'publish') $reason = 'مقصد منتشر نشده ('.$st.')';
                } else {
                    // Unknown internal URL — verify with a cached HEAD request (capped)
                    $key = strtok($abs, '#');
                    if (isset($headCache[$key])) { $reason = $headCache[$key]; }
                    elseif ($headChecks < 120) {
                        $headChecks++;
                        $resp = wp_remote_head($key, ['timeout'=>8, 'redirection'=>3, 'sslverify'=>false]);
                        $code = is_wp_error($resp) ? 0 : (int) wp_remote_retrieve_response_code($resp);
                        $r = ($code >= 400 || $code === 0) ? ('کد پاسخ '.($code ?: 'خطا').' (احتمال ۴۰۴)') : '';
                        $headCache[$key] = $r; $reason = $r;
                    }
                }
                if ($reason !== '') {
                    $broken[] = [
                        'source'=>get_the_title($p->ID) ?: '#'.$p->ID,
                        'edit'=>get_edit_post_link($p->ID,'raw'),
                        'url'=>$abs, 'anchor'=>$anchor ?: '(بدون انکر)', 'reason'=>$reason,
                    ];
                    if (count($broken) >= 200) break 2;
                }
            }
        }
        wp_send_json_success(['rows'=>$broken, 'checked'=>count($posts)]);
    }

    /** AI-powered cluster/silo analysis: best pillar + internal link plan + anchors. */
    public function ajax_ai_cluster(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!\ViraSEO\Api\AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $pages = json_decode(wp_unslash($_POST['pages'] ?? '[]'), true);
        if (!is_array($pages) || !$pages) wp_send_json_error('داده ناقص.');

        $list = '';
        foreach (array_slice($pages, 0, 25) as $i => $pg) {
            $list .= ($i+1).". {$pg['title']} [{$pg['type']}] — {$pg['url']}\n";
        }
        $system = 'شما معمار سئوی فارسی و متخصص ساختار Silo و لینک‌سازی داخلی هستید. '
                . 'پاسخ خود را به‌صورت JSON با ساختار زیر بده. بدون هیچ توضیح اضافه قبل یا بعد JSON:\n'
                . '{"pillar":{"url":"...","reason":"..."},"links":[{"from_url":"...","to_url":"...","anchor":"انکرتکست فارسی"}],"missing_content":["عنوان محتوای پیشنهادی"]}';
        $user = "موضوع خوشه: «{$keyword}»\nصفحات این خوشه:\n{$list}\n"
              . "نقشه‌ی لینک‌سازی داخلی سیلو بساز. دقیقاً مشخص کن:\n"
              . "- pillar (ستون) کدام URL باشد و دلیلش\n"
              . "- از کدام URL به کدام URL با چه انکرتکستی لینک بدهد (حداقل ۵ لینک)\n"
              . "- آیا محتوای جدیدی برای کامل‌شدن خوشه لازم است";
        $res = \ViraSEO\Api\AiClient::chat($system, $user, 0.4);
        if (isset($res['error'])) wp_send_json_error($res['error']);

        // Try to parse JSON from response
        $text = $res['text'];
        $structured = null;
        if (preg_match('/\{.*\}/s', $text, $jm)) {
            $structured = json_decode($jm[0], true);
        }

        wp_send_json_success([
            'text' => $text,
            'structured' => $structured,
            'cost' => $res['cost'],
            'tokens' => $res['tokens'],
        ]);
    }

    /**
     * Internal PageRank — distributes link equity across pages via the internal link graph.
     * Returns post_id => score (0-100, relative to the strongest page).
     */
    public function compute_link_scores(): array {
        global $wpdb;
        $edges = $wpdb->get_results("SELECT source_id, target_id FROM {$wpdb->prefix}viraseo_internal_links");
        $out = []; $nodes = [];
        foreach ($edges as $e) {
            $s = (int)$e->source_id; $t = (int)$e->target_id;
            if ($s < 1 || $t < 1) continue;
            $out[$s][] = $t; $nodes[$s] = true; $nodes[$t] = true;
        }
        $keys = array_keys($nodes);
        $N = count($keys);
        if ($N === 0) return [];
        $d = 0.85;
        $pr = array_fill_keys($keys, 1.0 / $N);
        for ($iter = 0; $iter < 25; $iter++) {
            $dangling = 0.0;
            foreach ($keys as $n) if (empty($out[$n])) $dangling += $pr[$n];
            $new = array_fill_keys($keys, (1 - $d) / $N + $d * ($dangling / $N));
            foreach ($out as $s => $targets) {
                $share = $pr[$s] / count($targets);
                foreach ($targets as $t) $new[$t] += $d * $share;
            }
            $pr = $new;
        }
        $max = max($pr) ?: 1;
        $scores = [];
        foreach ($pr as $n => $v) $scores[$n] = (int) round($v / $max * 100);
        return $scores;
    }

    /** Per-page internal link scores table (page, inlinks, score). */
    public function ajax_link_scores(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $scores = get_option('viraseo_link_scores', []);
        if (!is_array($scores) || !$scores) { wp_send_json_success(['rows'=>[]]); return; }
        $inlinks = [];
        foreach ($wpdb->get_results("SELECT target_id, COUNT(*) c FROM {$wpdb->prefix}viraseo_internal_links GROUP BY target_id") as $r) {
            $inlinks[(int)$r->target_id] = (int)$r->c;
        }
        arsort($scores);
        $rows = [];
        foreach (array_slice($scores, 0, 100, true) as $id => $sc) {
            $rows[] = [
                'id'=>$id,
                'title'=>get_the_title($id) ?: '(بدون عنوان)',
                'url'=>get_permalink($id),
                'score'=>$sc,
                'inlinks'=>$inlinks[$id] ?? 0,
            ];
        }
        wp_send_json_success(['rows'=>$rows]);
    }

    /** Graph nodes + edges (top pages by link score) for visualization. */
    public function ajax_link_graph(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $scores = get_option('viraseo_link_scores', []);
        if (!is_array($scores) || !$scores) { wp_send_json_success(['nodes'=>[], 'edges'=>[]]); return; }
        arsort($scores);
        $top = array_slice($scores, 0, 35, true);
        $ids = array_keys($top);
        $nodes = [];
        foreach ($top as $id => $sc) {
            $nodes[] = ['id'=>(int)$id, 'title'=>mb_substr(get_the_title($id) ?: ('#'.$id), 0, 28), 'score'=>$sc];
        }
        $edges = [];
        $idList = implode(',', array_map('intval', $ids));
        if ($idList) {
            $rows = $wpdb->get_results("SELECT DISTINCT source_id, target_id FROM {$wpdb->prefix}viraseo_internal_links WHERE source_id IN ({$idList}) AND target_id IN ({$idList})");
            foreach ($rows as $r) $edges[] = ['from'=>(int)$r->source_id, 'to'=>(int)$r->target_id];
        }
        wp_send_json_success(['nodes'=>$nodes, 'edges'=>$edges]);
    }

    public function ajax_scan(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        try {
            $result = $this->scan();
            // Generate link suggestions immediately (don't wait for cron)
            $sugg = $this->suggest();
            $msg = sprintf('✅ اسکن کامل شد. %d لینک داخلی، %d صفحه یتیم، %d پیشنهاد لینک.', $result['links'], $result['orphans'], $sugg['count']);
            if ($sugg['count'] === 0 && $sugg['attempted'] > 0) {
                $msg .= ' ⚠️ ' . $sugg['attempted'] . ' پیشنهاد ساخته شد ولی ذخیره نشد. خطای پایگاه داده: ' . ($sugg['error'] ?: 'نامشخص');
            }
            wp_send_json_success([
                'message' => $msg,
                'links' => $result['links'],
                'orphans' => $result['orphans'],
                'suggestions' => $sugg['count'],
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error('خطا در اسکن: ' . $e->getMessage());
        }
    }

    public function ajax_orphans(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $ptype = sanitize_text_field($_POST['post_type'] ?? '');
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_orphan_pages WHERE status IN ('orphan','low') ORDER BY inlinks LIMIT 1000");
        $tlabel = function($slug){ $o = get_post_type_object($slug); return $o ? $o->labels->singular_name : $slug; };
        $data = [];
        foreach ($rows as $r) {
            if ($ptype && $ptype !== 'all' && $r->post_type !== $ptype) continue;
            $data[] = [
                'id'=>$r->post_id,'title'=>$r->post_title?:get_the_title($r->post_id),
                'type'=>$tlabel($r->post_type),'inlinks'=>(int)$r->inlinks,'outlinks'=>(int)$r->outlinks,
                'status'=>$r->status,'url'=>get_permalink($r->post_id),'edit'=>get_edit_post_link($r->post_id,'raw'),
            ];
        }
        // Type filter options (only types that actually have orphans)
        $type_objs = []; $seen = [];
        foreach ($rows as $r) {
            if (isset($seen[$r->post_type])) continue;
            $seen[$r->post_type] = true;
            $o = get_post_type_object($r->post_type);
            $type_objs[] = ['slug'=>$r->post_type, 'label'=>$o ? $o->labels->name : $r->post_type];
        }
        wp_send_json_success(['rows'=>$data, 'types'=>$type_objs, 'total'=>count($data)]);
    }

    public function ajax_suggestions(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $type = sanitize_text_field($_POST['type'] ?? '');
        $ptype = sanitize_text_field($_POST['post_type'] ?? '');
        $where = "status='pending'";
        if (in_array($type, ['exact','partial','semantic'], true)) $where .= $wpdb->prepare(" AND match_type=%s", $type);
        // Fetch a larger set so we can filter by post type in PHP, then cap to 100
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_link_suggestions WHERE {$where} ORDER BY FIELD(match_type,'exact','partial','semantic'), score DESC LIMIT 500");
        $labels = ['exact'=>'دقیق','partial'=>'جزئی','semantic'=>'معنایی'];
        $tlabel = function($id){ $o = get_post_type_object(get_post_type($id)); return $o ? $o->labels->singular_name : get_post_type($id); };

        $data = [];
        foreach ($rows as $r) {
            $src_type = get_post_type($r->source_id);
            $tgt_type = get_post_type($r->target_id);
            // Filter: match if EITHER source or target is the selected type
            if ($ptype && $ptype !== 'all' && $src_type !== $ptype && $tgt_type !== $ptype) continue;
            $data[] = [
                'id'=>$r->id,
                'source'=>get_the_title($r->source_id) ?: '(بدون عنوان)',
                'source_edit'=>get_edit_post_link($r->source_id,'raw'),
                'source_url'=>get_permalink($r->source_id),
                'source_type'=>$tlabel($r->source_id),
                'target'=>get_the_title($r->target_id) ?: '(بدون عنوان)',
                'target_url'=>get_permalink($r->target_id),
                'target_edit'=>get_edit_post_link($r->target_id,'raw'),
                'target_type'=>$tlabel($r->target_id),
                'anchor'=>$r->anchor,'score'=>(float)$r->score,'reason'=>$r->reason,
                'type'=>$r->match_type ?: 'semantic',
                'type_label'=>$labels[$r->match_type ?? 'semantic'] ?? 'معنایی',
            ];
            if (count($data) >= 100) break;
        }
        // Counts per match type for the filter chips
        $counts = ['all'=>0,'exact'=>0,'partial'=>0,'semantic'=>0];
        foreach ($wpdb->get_results("SELECT match_type, COUNT(*) c FROM {$wpdb->prefix}viraseo_link_suggestions WHERE status='pending' GROUP BY match_type") as $c) {
            $counts[$c->match_type] = (int)$c->c; $counts['all'] += (int)$c->c;
        }
        $type_objs = [];
        foreach (\ViraSEO\Features\TargetKeywords::public_types() as $t) {
            $o = get_post_type_object($t);
            $type_objs[] = ['slug'=>$t, 'label'=>$o ? $o->labels->name : $t];
        }
        wp_send_json_success(['rows'=>$data, 'counts'=>$counts, 'types'=>$type_objs]);
    }

    public function ajax_accept(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $id = absint($_POST['id']??0);
        if ($id) $wpdb->update($wpdb->prefix.'viraseo_link_suggestions', ['status'=>'accepted'], ['id'=>$id]);
        wp_send_json_success();
    }

    public function ajax_reject(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $id = absint($_POST['id']??0);
        if ($id) $wpdb->update($wpdb->prefix.'viraseo_link_suggestions', ['status'=>'rejected'], ['id'=>$id]);
        wp_send_json_success();
    }

    /** Auto-insert a single suggested link into the source post's content. */
    public function ajax_apply(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('دسترسی غیرمجاز.');
        $id = absint($_POST['id']??0);
        $res = $this->apply_suggestion($id);
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success($res);
    }

    /** Auto-insert ALL accepted/pending suggestions (bulk). */
    public function ajax_apply_all(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}viraseo_link_suggestions WHERE status='pending' ORDER BY score DESC LIMIT 50");
        $done = 0; $fail = 0;
        foreach ($ids as $sid) {
            $r = $this->apply_suggestion((int)$sid);
            if (isset($r['error'])) $fail++; else $done++;
        }
        wp_send_json_success(['message'=>sprintf('✅ %d لینک به‌صورت خودکار درج شد. (%d مورد قابل درج نبود)', $done, $fail)]);
    }

    /** Core: insert the link into post content + mark suggestion applied. */
    private function apply_suggestion(int $id): array {
        global $wpdb;
        $st = $wpdb->prefix.'viraseo_link_suggestions';
        $s = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$st} WHERE id=%d", $id));
        if (!$s) return ['error'=>'پیشنهاد یافت نشد.'];

        $post = get_post($s->source_id);
        if (!$post) return ['error'=>'پست مبدا یافت نشد.'];
        $url = get_permalink($s->target_id);
        if (!$url) return ['error'=>'صفحه مقصد یافت نشد.'];

        // Already linked to target? skip (avoid duplicates)
        if (strpos($post->post_content, 'href="'.$url.'"') !== false || strpos($post->post_content, "href='".$url."'") !== false) {
            $wpdb->update($st, ['status'=>'accepted'], ['id'=>$id]);
            return ['error'=>'این لینک از قبل در محتوا وجود دارد.'];
        }

        $out = $this->insert_link_into_content($post->post_content, $s->anchor, $url);
        if (!$out['inserted']) return ['error'=>'انکر «'.$s->anchor.'» در متن مبدا پیدا نشد (یا داخل لینک دیگری بود).'];

        wp_update_post(['ID'=>$s->source_id, 'post_content'=>$out['content']]);
        $wpdb->update($st, ['status'=>'accepted'], ['id'=>$id]);

        // Record the new internal link so future scans/orphan counts are accurate
        $wpdb->insert($wpdb->prefix.'viraseo_internal_links', [
            'source_id'=>$s->source_id,'target_id'=>$s->target_id,
            'anchor'=>mb_substr($s->anchor,0,500),'link_url'=>$url,
        ]);
        return ['message'=>'✅ لینک با انکر «'.$s->anchor.'» در محتوا درج شد.'];
    }

    /**
     * Safely insert a link around the FIRST plain-text occurrence of $anchor.
     * Skips text inside existing <a> tags and inside HTML tags (RTL/Persian safe).
     */
    private function insert_link_into_content(string $content, string $anchor, string $url): array {
        $anchor = trim($anchor);
        if ($anchor === '') return ['content'=>$content, 'inserted'=>false];

        $tokens = preg_split('/(<[^>]+>)/u', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $inside_a = false; $inserted = false;
        $pattern = '/(?<![\p{L}\x{200C}])' . preg_quote($anchor, '/') . '(?![\p{L}\x{200C}])/u';

        foreach ($tokens as &$tok) {
            if ($tok === '') continue;
            if ($tok[0] === '<') {
                if (preg_match('/^<a\b/i', $tok)) $inside_a = true;
                elseif (preg_match('/^<\/a>/i', $tok)) $inside_a = false;
                continue;
            }
            if ($inserted || $inside_a) continue;
            $new = preg_replace_callback($pattern, function($m) use ($url, &$inserted) {
                if ($inserted) return $m[0];
                $inserted = true;
                return '<a href="'.esc_url($url).'">'.$m[0].'</a>';
            }, $tok, 1);
            if ($new !== null) $tok = $new;
        }
        unset($tok);
        return ['content'=>implode('', $tokens), 'inserted'=>$inserted];
    }

    /** Topical clustering: group pages that share significant keyword tokens — across ALL post types. */
    public function ajax_clusters(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        // ALL public types (product, post, page, landing CPTs...) — clustering is NOT bounded by type.
        $types = \ViraSEO\Features\TargetKeywords::public_types();
        $in = "'" . implode("','", array_map('esc_sql', $types)) . "'";
        $raw = $wpdb->get_results("SELECT ID,post_title,post_content,post_type FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ({$in}) LIMIT 800");
        $posts = [];
        foreach ($raw as $p) { if (!\ViraSEO\Features\TargetKeywords::is_excluded((int)$p->ID)) $posts[] = $p; }

        // Build unified NODES: posts + taxonomy terms (product_cat & category) so category
        // pages can be cluster pillars (silo). Each node has a string key: 'p'+ID / 'pc'+termID / 'c'+termID.
        $tokens = []; $meta = []; $df = [];
        $addTokens = function(string $key, array $set) use (&$tokens, &$df) {
            $tokens[$key] = $set;
            foreach (array_keys($set) as $t) $df[$t] = ($df[$t] ?? 0) + 1;
        };
        foreach ($posts as $p) {
            $key = 'p' . $p->ID;
            $set = [];
            foreach (\ViraSEO\Features\TargetKeywords::get_all((int)$p->ID) as $kwphrase) {
                foreach (PersianText::tokenize($kwphrase) as $t) if (mb_strlen($t) > 2) $set[$t] = true;
            }
            foreach (array_keys(PersianText::extract_keywords(wp_strip_all_tags($p->post_content).' '.$p->post_title, 8)) as $t) {
                if (mb_strlen($t) > 2) $set[$t] = true;
            }
            // Inject the post's assigned category/product-category term names as tokens.
            // This guarantees a product shares tokens with its product_cat term node, so the
            // category page becomes a reliable cluster pillar (silo head) for its products.
            foreach (['product_cat', 'category'] as $tax) {
                if (!taxonomy_exists($tax)) continue;
                $terms = get_the_terms($p->ID, $tax);
                if (is_array($terms)) {
                    foreach ($terms as $term) {
                        foreach (PersianText::tokenize($term->name) as $t) if (mb_strlen($t) > 2) $set[$t] = true;
                    }
                }
            }
            $pt = get_post_type_object($p->post_type);
            $meta[$key] = ['key'=>$key, 'ref'=>(int)$p->ID, 'is_term'=>false,
                'title'=>$p->post_title ?: get_the_title($p->ID), 'len'=>mb_strlen(wp_strip_all_tags($p->post_content)),
                'type'=>$pt ? $pt->labels->singular_name : $p->post_type, 'url'=>get_permalink($p->ID)];
            $addTokens($key, $set);
        }
        // Taxonomy terms as silo pillars
        $taxes = ['product_cat'=>'pc', 'category'=>'c'];
        foreach ($taxes as $tax => $prefix) {
            if (!taxonomy_exists($tax)) continue;
            $terms = get_terms(['taxonomy'=>$tax, 'hide_empty'=>false, 'number'=>200]);
            if (is_wp_error($terms)) continue;
            $tobj = get_taxonomy($tax);
            foreach ($terms as $term) {
                $link = get_term_link($term);
                if (is_wp_error($link)) continue;
                $key = $prefix . $term->term_id;
                $set = [];
                $kw = (string) get_term_meta($term->term_id, '_viraseo_target_keyword', true);
                foreach (PersianText::tokenize($kw . ' ' . $term->name) as $t) if (mb_strlen($t) > 2) $set[$t] = true;
                foreach (array_keys(PersianText::extract_keywords($term->name.' '.$term->description, 6)) as $t) {
                    if (mb_strlen($t) > 2) $set[$t] = true;
                }
                if (!$set) continue;
                $meta[$key] = ['key'=>$key, 'ref'=>(int)$term->term_id, 'is_term'=>true, 'tax'=>$tax,
                    'title'=>$term->name, 'len'=>mb_strlen($term->description),
                    // Categories are favored as pillars (weight from product/post count)
                    'weight'=> 50 + (int)$term->count * 2,
                    'type'=> $tobj ? $tobj->labels->singular_name : $tax, 'url'=>$link];
                $addTokens($key, $set);
            }
        }
        if (count($meta) < 2) { wp_send_json_success(['clusters'=>[]]); return; }

        // Candidate topics = tokens shared by ≥2 nodes, most frequent first.
        $candidates = array_filter($df, fn($c) => $c >= 2);
        arsort($candidates);

        // Existing link pairs (post→post) + inlinks + GSC impressions for pillar weighting
        $inlinks = []; $pairs = [];
        foreach ($wpdb->get_results("SELECT source_id, target_id FROM {$wpdb->prefix}viraseo_internal_links") as $r) {
            $pairs['p'.(int)$r->source_id . '-p' . (int)$r->target_id] = true;
        }
        foreach ($wpdb->get_results("SELECT target_id, COUNT(*) c FROM {$wpdb->prefix}viraseo_internal_links GROUP BY target_id") as $r) {
            $inlinks['p'.(int)$r->target_id] = (int)$r->c;
        }
        $impr = [];
        $gt = $wpdb->prefix.'viraseo_gsc_keywords';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$gt}'") === $gt) {
            foreach ($wpdb->get_results("SELECT page_url, SUM(impressions) i FROM {$gt} GROUP BY page_url") as $r) {
                $pid = url_to_postid($r->page_url); if ($pid) $impr['p'.$pid] = (int)$r->i;
            }
        }
        $weight = function(array $m) use ($inlinks, $impr) {
            return ($m['weight'] ?? 0) + ($inlinks[$m['key']] ?? 0) + ($impr[$m['key']] ?? 0) / 100;
        };

        // Greedy assignment: each node joins the highest-DF topic token it contains (once).
        $assigned = []; $out = [];
        foreach (array_keys($candidates) as $topic) {
            $members = [];
            foreach ($meta as $key => $node) {
                if (isset($assigned[$key])) continue;
                if (isset($tokens[$key][$topic])) $members[] = $node;
            }
            if (count($members) < 2) continue;
            foreach ($members as $m) $assigned[$m['key']] = true;
            usort($members, function($a, $b) use ($weight) {
                return ($weight($b) <=> $weight($a)) ?: (($b['len'] ?? 0) <=> ($a['len'] ?? 0));
            });
            $pillar = $members[0];
            $pkey = $pillar['key'];
            $rest = array_slice($members, 1, 30);

            $linked = 0;
            foreach ($rest as $m) if (isset($pairs[$m['key'] . '-' . $pkey])) $linked++;
            $coverage = count($rest) ? round($linked * 100 / count($rest)) : 100;
            $clusterImpr = $impr[$pkey] ?? 0;
            foreach ($rest as $m) $clusterImpr += ($impr[$m['key']] ?? 0);

            $nodeOut = fn($m) => ['id'=>$m['key'], 'title'=>$m['title'], 'type'=>$m['type'], 'url'=>$m['url'], 'is_term'=>!empty($m['is_term'])];
            $out[] = [
                'keyword'=>$topic,
                'count'=>count($members),
                'coverage'=>$coverage,
                'impressions'=>PersianText::format_number($clusterImpr),
                'pillar_id'=>$pkey,
                'pillar'=>array_merge($nodeOut($pillar), ['edit'=> $pillar['is_term'] ? get_edit_term_link($pillar['ref'], $pillar['tax']) : get_edit_post_link($pillar['ref'],'raw')]),
                'members'=>array_map(fn($m)=>array_merge($nodeOut($m), ['linked'=> isset($pairs[$m['key'] . '-' . $pkey])]), $rest),
            ];
        }
        usort($out, fn($a, $b) => $b['count'] <=> $a['count']);
        wp_send_json_success(['clusters'=>array_slice($out, 0, 40)]);
    }

    /** Decode a cluster node key ('p123' post, 'pc12'/'c12' term) into [kind,id,tax]. */
    private function decode_node(string $key): array {
        if (preg_match('/^pc(\d+)$/', $key, $m)) return ['kind'=>'term', 'id'=>(int)$m[1], 'tax'=>'product_cat'];
        if (preg_match('/^c(\d+)$/', $key, $m))  return ['kind'=>'term', 'id'=>(int)$m[1], 'tax'=>'category'];
        if (preg_match('/^p(\d+)$/', $key, $m))  return ['kind'=>'post', 'id'=>(int)$m[1]];
        return ['kind'=>'post', 'id'=>(int)$key];
    }

    /** Auto-link selected member pages UP to the cluster pillar (build the silo).
     *  Pillar/members are node keys ('p123' post, 'pc12'/'c12' term). */
    public function ajax_cluster_link(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $pillar_key = sanitize_text_field($_POST['pillar_id'] ?? '');
        $members = array_map('sanitize_text_field', (array)($_POST['members'] ?? []));
        if (!$pillar_key || !$members) wp_send_json_error('داده ناقص.');

        $pd = $this->decode_node($pillar_key);
        if ($pd['kind'] === 'term') {
            $link = get_term_link($pd['id'], $pd['tax']);
            if (is_wp_error($link)) wp_send_json_error('صفحه دسته‌بندی یافت نشد.');
            $url = $link;
            $term = get_term($pd['id'], $pd['tax']);
            $anchor = (string) get_term_meta($pd['id'], '_viraseo_target_keyword', true);
            $title = $term ? $term->name : 'دسته‌بندی';
            if ($anchor === '') $anchor = $title;
        } else {
            $url = get_permalink($pd['id']);
            if (!$url) wp_send_json_error('صفحه ستون یافت نشد.');
            $title = get_the_title($pd['id']);
            $anchor = TargetKeywords::get($pd['id']);
            if ($anchor === '') { $kw = PersianText::extract_keywords($title, 1); $anchor = $kw ? array_key_first($kw) : $title; }
        }

        $linked = 0; $skipped = 0;
        foreach ($members as $mkey) {
            if ($mkey === $pillar_key) continue;
            $md = $this->decode_node($mkey);
            if ($md['kind'] !== 'post') { $skipped++; continue; } // only posts can be edited as sources
            $mid = $md['id'];
            $post = get_post($mid);
            if (!$post) { $skipped++; continue; }
            if (strpos($post->post_content, 'href="'.$url.'"') !== false) { $skipped++; continue; }
            $out = $this->insert_link_into_content($post->post_content, $anchor, $url);
            $content = $out['inserted'] ? $out['content']
                : $post->post_content . "\n\n<p>بیشتر بخوانید: <a href=\"" . esc_url($url) . "\">" . esc_html($title) . "</a></p>";
            wp_update_post(['ID'=>$mid, 'post_content'=>$content]);
            // Record only post→post links (internal_links target is a post id)
            if ($pd['kind'] === 'post') {
                $wpdb->insert($wpdb->prefix.'viraseo_internal_links', ['source_id'=>$mid,'target_id'=>$pd['id'],'anchor'=>mb_substr($anchor,0,500),'link_url'=>$url]);
            }
            $linked++;
        }
        $note = $skipped ? " ($skipped مورد رد شد — فقط نوشته‌ها/برگه‌ها قابل ویرایش‌اند)" : '';
        wp_send_json_success(['message'=>sprintf('✅ %d صفحه به ستون «%s» لینک شد.%s', $linked, $title, $note)]);
    }

    /**
     * Compute global internal link health score (0-100) based on 5 weighted factors.
     * Result is cached for the request lifecycle to avoid redundant SQL queries.
     */
    public function compute_link_health(): array {
        if ($this->cached_health !== null) {
            return $this->cached_health;
        }

        global $wpdb;
        $lt = $wpdb->prefix . 'viraseo_internal_links';

        // Total published pages
        $total_pages = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product')");
        if ($total_pages < 1) {
            return ['score' => 0, 'factors' => [
                'orphan' => ['score' => 0, 'detail' => 'صفحه منتشرشده‌ای یافت نشد.'],
                'avg_inlinks' => ['score' => 0, 'detail' => 'داده‌ای نیست.'],
                'distribution' => ['score' => 0, 'detail' => 'داده‌ای نیست.'],
                'broken' => ['score' => 70, 'detail' => 'بررسی نشده.'],
                'coverage' => ['score' => 0, 'detail' => 'داده‌ای نیست.'],
            ]];
        }

        // Inlinks per page
        $inlinks_data = $wpdb->get_results("SELECT target_id, COUNT(*) c FROM {$lt} GROUP BY target_id");
        $inlinks_map = [];
        foreach ($inlinks_data as $r) $inlinks_map[(int) $r->target_id] = (int) $r->c;

        // Outlinks per page
        $outlinks_data = $wpdb->get_results("SELECT source_id, COUNT(*) c FROM {$lt} GROUP BY source_id");
        $outlinks_map = [];
        foreach ($outlinks_data as $r) $outlinks_map[(int) $r->source_id] = (int) $r->c;

        // All published page IDs
        $page_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product')");

        // Factor 1: Orphan ratio (weight 25)
        $orphan_count = 0;
        foreach ($page_ids as $pid) {
            if (empty($inlinks_map[(int) $pid])) $orphan_count++;
        }
        $orphan_ratio = $orphan_count / $total_pages * 100;
        $orphan_score = max(0, (int) round(100 - $orphan_ratio));
        $orphan_detail = PersianText::format_number($orphan_count) . ' صفحه یتیم از ' . PersianText::format_number($total_pages) . ' صفحه';

        // Factor 2: Average inlinks (weight 20)
        $total_inlinks = array_sum($inlinks_map);
        $avg = $total_pages > 0 ? $total_inlinks / $total_pages : 0;
        if ($avg >= 6) $avg_score = 100;
        elseif ($avg >= 3) $avg_score = 70;
        elseif ($avg >= 1) $avg_score = 40;
        else $avg_score = 0;
        $avg_detail = 'میانگین ' . PersianText::format_number(round($avg, 1)) . ' لینک ورودی به هر صفحه';

        // Factor 3: Distribution (weight 20) - how evenly link equity is spread
        $dist_score = 100;
        if ($total_inlinks > 0 && count($inlinks_map) > 0) {
            $sorted_inlinks = array_values($inlinks_map);
            rsort($sorted_inlinks);
            $top5 = array_sum(array_slice($sorted_inlinks, 0, 5));
            $top5_pct = $top5 / $total_inlinks * 100;
            if ($top5_pct > 80) $dist_score = 20;
            elseif ($top5_pct > 60) $dist_score = 50;
            elseif ($top5_pct > 40) $dist_score = 80;
            else $dist_score = 100;
        }
        $dist_detail = '۵ صفحه برتر ' . PersianText::format_number((int) round($top5_pct ?? 0)) . '٪ از لینک‌ها را دارند';

        // Factor 4: Broken ratio (weight 15)
        $broken_cache = get_transient('viraseo_broken_count');
        if ($broken_cache !== false) {
            $broken_count = (int) $broken_cache;
            $total_links = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$lt}");
            $broken_score = $total_links > 0 ? max(0, (int) round(100 - ($broken_count / $total_links * 100))) : 100;
            $broken_detail = PersianText::format_number($broken_count) . ' لینک شکسته از ' . PersianText::format_number($total_links) . ' لینک';
        } else {
            $broken_score = 70;
            $broken_detail = 'هنوز بررسی نشده (پیش‌فرض ۷۰)';
        }

        // Factor 5: Coverage (weight 20) - pages with BOTH inlink AND outlink
        $covered = 0;
        foreach ($page_ids as $pid) {
            $pid = (int) $pid;
            if (!empty($inlinks_map[$pid]) && !empty($outlinks_map[$pid])) $covered++;
        }
        $coverage_pct = $total_pages > 0 ? (int) round($covered / $total_pages * 100) : 0;
        $coverage_score = $coverage_pct;
        $coverage_detail = PersianText::format_number($covered) . ' صفحه از ' . PersianText::format_number($total_pages) . ' هم لینک ورودی و هم خروجی دارند (' . PersianText::format_number($coverage_pct) . '٪)';

        // Weighted total
        $score = (int) round(
            $orphan_score * 0.25 +
            $avg_score * 0.20 +
            $dist_score * 0.20 +
            $broken_score * 0.15 +
            $coverage_score * 0.20
        );

        $this->cached_health = [
            'score' => $score,
            'factors' => [
                'orphan' => ['score' => $orphan_score, 'detail' => $orphan_detail],
                'avg_inlinks' => ['score' => $avg_score, 'detail' => $avg_detail],
                'distribution' => ['score' => $dist_score, 'detail' => $dist_detail],
                'broken' => ['score' => $broken_score, 'detail' => $broken_detail],
                'coverage' => ['score' => $coverage_score, 'detail' => $coverage_detail],
            ],
        ];

        return $this->cached_health;
    }

    /** AJAX: Get current link health score + comparison with previous entry. */
    public function ajax_link_health(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $current = $this->compute_link_health();
        $history = get_option('viraseo_link_health_history', []);
        if (!is_array($history)) $history = [];

        $comparison = null;
        if (count($history) >= 1) {
            $prev = end($history);
            // If current date matches last entry, compare with the one before
            $today = date('Y-m-d');
            if (isset($prev['date_raw']) && $prev['date_raw'] === $today && count($history) >= 2) {
                $prev = $history[count($history) - 2];
            }
            $delta = $current['score'] - ($prev['score'] ?? 0);
            $comparison = [
                'prev_score' => $prev['score'] ?? 0,
                'prev_date' => $prev['date'] ?? '',
                'delta' => $delta,
                'trend' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'same'),
            ];
        }

        wp_send_json_success([
            'score' => $current['score'],
            'factors' => $current['factors'],
            'comparison' => $comparison,
        ]);
    }

    /** AJAX: Get all link health history entries. */
    public function ajax_link_health_history(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $history = get_option('viraseo_link_health_history', []);
        if (!is_array($history)) $history = [];

        wp_send_json_success(['entries' => $history]);
    }

    /**
     * Record a link health snapshot (max 1 per day).
     */
    private function record_link_health_snapshot(): void {
        $current = $this->compute_link_health();
        $history = get_option('viraseo_link_health_history', []);
        if (!is_array($history)) $history = [];

        $today = date('Y-m-d');
        // Check if today is already recorded
        foreach ($history as $entry) {
            if (isset($entry['date_raw']) && $entry['date_raw'] === $today) return;
        }

        $history[] = [
            'date' => \ViraSEO\Utils\JalaliDate::format($today, 'relative'),
            'date_raw' => $today,
            'score' => $current['score'],
            'factors' => $current['factors'],
        ];

        // Keep last 60 entries max
        if (count($history) > 60) $history = array_slice($history, -60);

        update_option('viraseo_link_health_history', $history);
    }

    public function scan(): array {
        global $wpdb;
        $lt = $wpdb->prefix.'viraseo_internal_links';
        $ot = $wpdb->prefix.'viraseo_orphan_pages';
        $host = wp_parse_url(get_site_url(), PHP_URL_HOST);

        $posts = $wpdb->get_results("SELECT ID,post_title,post_content,post_type FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') LIMIT 500");
        if (!$posts) return ['links'=>0,'orphans'=>0];

        $wpdb->query("DELETE FROM {$lt}");
        $link_count = 0;
        foreach ($posts as $p) {
            if (empty($p->post_content)) continue;
            preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $p->post_content, $m, PREG_SET_ORDER);
            foreach ($m as $match) {
                $href = $match[1]; $anchor = wp_strip_all_tags($match[2]);
                if (strpos($href,'/')===0) $href = get_site_url().$href;
                $lh = wp_parse_url($href, PHP_URL_HOST);
                if (!$lh || $lh !== $host) continue;
                $tid = url_to_postid($href);
                if (!$tid || $tid === $p->ID) continue;
                $wpdb->insert($lt, ['source_id'=>$p->ID,'target_id'=>$tid,'anchor'=>mb_substr($anchor,0,500),'link_url'=>$href]);
                $link_count++;
            }
        }

        $wpdb->query("DELETE FROM {$ot}");
        $wpdb->query("
            INSERT INTO {$ot} (post_id,post_type,post_title,inlinks,outlinks,status)
            SELECT p.ID, p.post_type, p.post_title,
                   COALESCE(i.c,0), COALESCE(o.c,0),
                   CASE WHEN COALESCE(i.c,0)=0 THEN 'orphan' WHEN COALESCE(i.c,0)<=2 THEN 'low' ELSE 'ok' END
            FROM {$wpdb->posts} p
            LEFT JOIN (SELECT target_id,COUNT(*) c FROM {$lt} GROUP BY target_id) i ON i.target_id=p.ID
            LEFT JOIN (SELECT source_id,COUNT(*) c FROM {$lt} GROUP BY source_id) o ON o.source_id=p.ID
            WHERE p.post_status='publish' AND p.post_type IN ('post','page','product') AND COALESCE(i.c,0)<=2
        ");

        $orphan_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$ot} WHERE status='orphan'");
        update_option('viraseo_link_scores', $this->compute_link_scores());
        update_option('viraseo_last_scan', current_time('mysql'));

        // Record link health snapshot (max 1 per day)
        $this->record_link_health_snapshot();

        return ['links'=>$link_count, 'orphans'=>$orphan_count];
    }

    public function suggest(): array {
        global $wpdb;
        $st = $wpdb->prefix.'viraseo_link_suggestions';
        $lt = $wpdb->prefix.'viraseo_internal_links';

        // Self-heal: older installs have an INCOMPATIBLE table (e.g. a unique key
        // 'unique_source_target' on columns we don't populate → "Duplicate entry '0-0'").
        // Since suggestions are regenerable, drop & recreate when the structure is stale.
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$st}");
        $idx = $wpdb->get_results("SHOW INDEX FROM {$st}");
        $keynames = $idx ? array_map(fn($i)=>$i->Key_name, $idx) : [];
        $needed = ['source_id','target_id','anchor','score','match_type','reason','status'];
        $stale = (bool) array_diff($needed, $cols ?: [])
                 || !in_array('uq_pair', $keynames, true)
                 || in_array('unique_source_target', $keynames, true);
        if (!$cols || $stale) {
            $wpdb->query("DROP TABLE IF EXISTS {$st}");
            (new \ViraSEO\Database\Schema())->create_all_tables();
        }

        // Clear old pending suggestions (keep accepted/rejected)
        $wpdb->query("DELETE FROM {$st} WHERE status='pending'");

        $posts = $wpdb->get_results("SELECT ID,post_title,post_content FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') LIMIT 300");
        if (count($posts)<2) return ['count'=>0,'attempted'=>0,'error'=>''];

        // Pre-compute normalized text + keyword sets + ALL target keywords (primary+secondary) per post
        $norm = []; $cache = []; $target = [];
        foreach ($posts as $p) {
            $text = wp_strip_all_tags($p->post_content) . ' ' . $p->post_title;
            $norm[$p->ID] = PersianText::normalize(mb_strtolower($text));
            $cache[$p->ID] = PersianText::extract_keywords($text, 25);
            $target[$p->ID] = array_slice(array_map(fn($k)=>mb_strtolower($k), TargetKeywords::get_all((int)$p->ID)), 0, 3);
        }

        $count = 0; $attempted = 0; $error = '';
        $insert = function(int $src, int $tgt, string $anchor, float $score, string $type, string $reason) use ($wpdb, $st, $lt, &$count, &$attempted, &$error): bool {
            if ($src < 1 || $tgt < 1 || $src === $tgt) return false;
            if ($wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$lt} WHERE source_id=%d AND target_id=%d", $src, $tgt))) return false;
            if ($wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$st} WHERE source_id=%d AND target_id=%d", $src, $tgt))) return false;
            $attempted++;
            $ok = $wpdb->insert($st, ['source_id'=>$src,'target_id'=>$tgt,'anchor'=>mb_substr($anchor,0,500),'score'=>$score,'match_type'=>$type,'reason'=>mb_substr($reason,0,200),'status'=>'pending']);
            if ($ok === false) { if (!$error) $error = $wpdb->last_error; return false; }
            $count++; return true;
        };

        // ===== PASS 1 & 2: keyword-targeted linking (EXACT + PARTIAL) — uses primary + secondary keywords =====
        foreach ($posts as $b) {
            if ($count >= 200) break;
            if (TargetKeywords::is_excluded((int)$b->ID)) continue; // skip cart/checkout/account/noindex targets
            foreach ($target[$b->ID] as $kw) {
                if ($count >= 200) break;
                if ($kw === '' || mb_strlen($kw) < 3) continue;
                $kwTokens = array_values(array_filter(PersianText::tokenize($kw), fn($w)=>mb_strlen($w) > 2));
                $tokenCount = count($kwTokens);
                $isPrimary = ($kw === ($target[$b->ID][0] ?? ''));
                foreach ($posts as $a) {
                    if ($count >= 200) break;
                    if ($a->ID === $b->ID) continue;
                    $content = $norm[$a->ID];
                    $tag = $isPrimary ? '' : ' (فرعی)';

                    if (mb_strpos($content, $kw) !== false) {
                        $freq = substr_count($content, $kw);
                        $insert($a->ID, $b->ID, $kw, min(100, ($isPrimary?80:70) + $freq * 4), 'exact', 'تطابق دقیق'.$tag.': متن مبدا شامل عبارت کامل «'.$kw.'» است.');
                    } elseif ($tokenCount >= 2) {
                        $hit = 0;
                        foreach ($kwTokens as $tok) if (mb_strpos($content, $tok) !== false) $hit++;
                        $coverage = $hit / $tokenCount;
                        if ($coverage >= 0.6) {
                            $insert($a->ID, $b->ID, $kw, round(40 + $coverage * 30, 1), 'partial', 'تطابق جزئی'.$tag.': '.$hit.' از '.$tokenCount.' کلمه‌ی عبارت هدف در متن مبدا هست.');
                        }
                    }
                }
            }
        }

        // ===== PASS 3: SEMANTIC (shared keywords / topical relatedness) =====
        for ($i=0; $i<count($posts) && $count<200; $i++) {
            for ($j=$i+1; $j<count($posts) && $count<200; $j++) {
                $a=$posts[$i]; $b=$posts[$j];
                $ka=array_keys($cache[$a->ID]); $kb=array_keys($cache[$b->ID]);
                if (empty($ka) || empty($kb)) continue;
                $shared=array_intersect($ka,$kb);
                if (count($shared) < 2) continue; // need real semantic overlap
                $union=array_unique(array_merge($ka,$kb));
                $sim = count($shared)/max(1,count($union));
                if ($sim < 0.12) continue;

                $score = round($sim*100,2);
                $reason = 'ارتباط معنایی — کلمات مشترک: '.implode('، ', array_slice($shared,0,4));
                $pick = function(array $shared, string $title) {
                    $tt = PersianText::tokenize($title);
                    foreach ($shared as $kw) if (in_array($kw, $tt, true)) return $kw;
                    return reset($shared);
                };
                $insert($a->ID, $b->ID, $pick($shared, $b->post_title), $score, 'semantic', $reason);
                if ($count<200) $insert($b->ID, $a->ID, $pick($shared, $a->post_title), $score, 'semantic', $reason);
            }
        }
        return ['count'=>$count,'attempted'=>$attempted,'error'=>$error];
    }
}
