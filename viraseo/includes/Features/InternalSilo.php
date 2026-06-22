<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\PersianText;

/** Feature 3: Internal Links + Orphan Pages + Suggestions [🟢 مستقل] */
class InternalSilo {
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
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_orphan_pages WHERE status IN ('orphan','low') ORDER BY inlinks LIMIT 50");
        $data = array_map(fn($r)=>[
            'id'=>$r->post_id,'title'=>$r->post_title?:get_the_title($r->post_id),
            'type'=>$r->post_type,'inlinks'=>(int)$r->inlinks,'outlinks'=>(int)$r->outlinks,
            'status'=>$r->status,'url'=>get_permalink($r->post_id),'edit'=>get_edit_post_link($r->post_id,'raw'),
        ], $rows);
        wp_send_json_success(['rows'=>$data]);
    }

    public function ajax_suggestions(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_link_suggestions WHERE status='pending' ORDER BY score DESC LIMIT 30");
        $data = array_map(fn($r)=>[
            'id'=>$r->id,
            'source'=>get_the_title($r->source_id),'source_edit'=>get_edit_post_link($r->source_id,'raw'),
            'target'=>get_the_title($r->target_id),'target_url'=>get_permalink($r->target_id),
            'anchor'=>$r->anchor,'score'=>(float)$r->score,'reason'=>$r->reason,
        ], $rows);
        wp_send_json_success(['rows'=>$data]);
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

    /** Topical clustering: group posts by their dominant shared keyword (silo view). */
    public function ajax_clusters(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $posts = $wpdb->get_results("SELECT ID,post_title,post_content FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') LIMIT 300");
        if (count($posts) < 2) { wp_send_json_success(['clusters'=>[]]); return; }

        // Top keyword per post → cluster key
        $clusters = [];
        foreach ($posts as $p) {
            $kw = PersianText::extract_keywords(wp_strip_all_tags($p->post_content).' '.$p->post_title, 5);
            if (!$kw) continue;
            $top = array_key_first($kw);
            $clusters[$top][] = ['id'=>$p->ID, 'title'=>$p->post_title ?: get_the_title($p->ID), 'len'=>mb_strlen(wp_strip_all_tags($p->post_content))];
        }

        // Inlink counts to pick the pillar (most-linked page in each cluster)
        $inlinks = [];
        foreach ($wpdb->get_results("SELECT target_id, COUNT(*) c FROM {$wpdb->prefix}viraseo_internal_links GROUP BY target_id") as $r) {
            $inlinks[(int)$r->target_id] = (int)$r->c;
        }

        $out = [];
        foreach ($clusters as $kw => $members) {
            if (count($members) < 2) continue; // only real clusters
            // Pillar = most inlinks, tie-break longest content
            usort($members, function($a, $b) use ($inlinks) {
                $ia = $inlinks[$a['id']] ?? 0; $ib = $inlinks[$b['id']] ?? 0;
                return $ib <=> $ia ?: $b['len'] <=> $a['len'];
            });
            $pillar = $members[0];
            $out[] = [
                'keyword'=>$kw,
                'count'=>count($members),
                'pillar'=>['title'=>$pillar['title'], 'url'=>get_permalink($pillar['id']), 'edit'=>get_edit_post_link($pillar['id'],'raw')],
                'members'=>array_map(fn($m)=>['title'=>$m['title'], 'url'=>get_permalink($m['id'])], array_slice($members, 1, 12)),
            ];
        }
        usort($out, fn($a, $b) => $b['count'] <=> $a['count']);
        wp_send_json_success(['clusters'=>array_slice($out, 0, 30)]);
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
        update_option('viraseo_last_scan', current_time('mysql'));

        return ['links'=>$link_count, 'orphans'=>$orphan_count];
    }

    public function suggest(): array {
        global $wpdb;
        $st = $wpdb->prefix.'viraseo_link_suggestions';
        $lt = $wpdb->prefix.'viraseo_internal_links';

        // Self-heal: ensure the table + columns are correct (older installs may be missing 'reason')
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$st}");
        if (!$cols || !in_array('reason', $cols, true) || !in_array('score', $cols, true)) {
            (new \ViraSEO\Database\Schema())->create_all_tables();
        }

        // Clear old pending suggestions (keep accepted/rejected)
        $wpdb->query("DELETE FROM {$st} WHERE status='pending'");

        $posts = $wpdb->get_results("SELECT ID,post_title,post_content FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') LIMIT 300");
        if (count($posts)<2) return ['count'=>0,'attempted'=>0,'error'=>''];

        // Build keyword cache for each post
        $cache = [];
        foreach ($posts as $p) {
            $text = wp_strip_all_tags($p->post_content) . ' ' . $p->post_title;
            $cache[$p->ID] = PersianText::extract_keywords($text, 25);
        }

        $count = 0; $attempted = 0; $error = '';
        $insert = function(int $src, int $tgt, string $anchor, float $score, string $reason) use ($wpdb, $st, $lt, &$count, &$attempted, &$error): void {
            if ($wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$lt} WHERE source_id=%d AND target_id=%d", $src, $tgt))) return;
            $attempted++;
            $ok = $wpdb->insert($st, ['source_id'=>$src,'target_id'=>$tgt,'anchor'=>mb_substr($anchor,0,500),'score'=>$score,'reason'=>mb_substr($reason,0,200),'status'=>'pending']);
            if ($ok === false) { if (!$error) $error = $wpdb->last_error; }
            else $count++;
        };

        for ($i=0; $i<count($posts) && $count<150; $i++) {
            for ($j=$i+1; $j<count($posts) && $count<150; $j++) {
                $a=$posts[$i]; $b=$posts[$j];
                $ka=array_keys($cache[$a->ID]); $kb=array_keys($cache[$b->ID]);
                if (empty($ka) || empty($kb)) continue;
                $shared=array_intersect($ka,$kb);
                if (empty($shared)) continue;
                $union=array_unique(array_merge($ka,$kb));
                $sim = count($shared)/count($union);
                if ($sim < 0.08) continue; // lower threshold = more suggestions

                $anchor = reset($shared);
                $score = round($sim*100,2);
                $reason = 'کلمات مشترک: '.implode('، ', array_slice($shared,0,4));

                // Smart anchor: prefer a shared keyword that appears in the TARGET's title
                // (more topically relevant), otherwise the highest-frequency shared keyword.
                $pick = function(array $shared, string $title) {
                    $tt = PersianText::tokenize($title);
                    foreach ($shared as $kw) if (in_array($kw, $tt, true)) return $kw;
                    return reset($shared);
                };
                $anchorAB = $pick($shared, $b->post_title);
                $anchorBA = $pick($shared, $a->post_title);

                $insert($a->ID, $b->ID, $anchorAB, $score, $reason);
                if ($count<150) $insert($b->ID, $a->ID, $anchorBA, $score, $reason);
            }
        }
        return ['count'=>$count,'attempted'=>$attempted,'error'=>$error];
    }
}
