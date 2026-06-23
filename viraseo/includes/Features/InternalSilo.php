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
        add_action('wp_ajax_viraseo_link_graph', [$this, 'ajax_link_graph']);
        add_action('wp_ajax_viraseo_link_scores', [$this, 'ajax_link_scores']);
        add_action('wp_ajax_viraseo_cluster_link', [$this, 'ajax_cluster_link']);
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
        $type = sanitize_text_field($_POST['type'] ?? '');
        $where = "status='pending'";
        if (in_array($type, ['exact','partial','semantic'], true)) $where .= $wpdb->prepare(" AND match_type=%s", $type);
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_link_suggestions WHERE {$where} ORDER BY FIELD(match_type,'exact','partial','semantic'), score DESC LIMIT 80");
        $labels = ['exact'=>'دقیق','partial'=>'جزئی','semantic'=>'معنایی'];
        $data = array_map(fn($r)=>[
            'id'=>$r->id,
            'source'=>get_the_title($r->source_id) ?: '(بدون عنوان)',
            'source_edit'=>get_edit_post_link($r->source_id,'raw'),
            'source_url'=>get_permalink($r->source_id),
            'target'=>get_the_title($r->target_id) ?: '(بدون عنوان)',
            'target_url'=>get_permalink($r->target_id),
            'target_edit'=>get_edit_post_link($r->target_id,'raw'),
            'anchor'=>$r->anchor,'score'=>(float)$r->score,'reason'=>$r->reason,
            'type'=>$r->match_type ?: 'semantic',
            'type_label'=>$labels[$r->match_type ?? 'semantic'] ?? 'معنایی',
        ], $rows);
        // Counts per type for the filter chips
        $counts = ['all'=>0,'exact'=>0,'partial'=>0,'semantic'=>0];
        foreach ($wpdb->get_results("SELECT match_type, COUNT(*) c FROM {$wpdb->prefix}viraseo_link_suggestions WHERE status='pending' GROUP BY match_type") as $c) {
            $counts[$c->match_type] = (int)$c->c; $counts['all'] += (int)$c->c;
        }
        wp_send_json_success(['rows'=>$data, 'counts'=>$counts]);
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

    /** Topical clustering: group pages that share a significant keyword token (silo view). */
    public function ajax_clusters(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $posts = $wpdb->get_results("SELECT ID,post_title,post_content FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') LIMIT 600");
        if (count($posts) < 2) { wp_send_json_success(['clusters'=>[]]); return; }

        // Build a token set per post (target keyword tokens + top content keywords) and document frequency.
        $tokens = []; $meta = []; $df = [];
        foreach ($posts as $p) {
            $set = [];
            $tk = TargetKeywords::get((int)$p->ID);
            foreach (PersianText::tokenize($tk) as $t) if (mb_strlen($t) > 2) $set[$t] = true;
            foreach (array_keys(PersianText::extract_keywords(wp_strip_all_tags($p->post_content).' '.$p->post_title, 8)) as $t) {
                if (mb_strlen($t) > 2) $set[$t] = true;
            }
            $tokens[$p->ID] = $set;
            $meta[$p->ID] = ['id'=>$p->ID, 'title'=>$p->post_title ?: get_the_title($p->ID), 'len'=>mb_strlen(wp_strip_all_tags($p->post_content))];
            foreach (array_keys($set) as $t) $df[$t] = ($df[$t] ?? 0) + 1;
        }

        // Candidate topics = tokens shared by ≥2 pages, most frequent first.
        $candidates = array_filter($df, fn($c) => $c >= 2);
        arsort($candidates);

        // Inlink counts for pillar selection + existing link pairs (source->target) + GSC impressions
        $inlinks = []; $pairs = [];
        foreach ($wpdb->get_results("SELECT source_id, target_id, COUNT(*) c FROM {$wpdb->prefix}viraseo_internal_links GROUP BY source_id, target_id") as $r) {
            $pairs[(int)$r->source_id . '-' . (int)$r->target_id] = true;
        }
        foreach ($wpdb->get_results("SELECT target_id, COUNT(*) c FROM {$wpdb->prefix}viraseo_internal_links GROUP BY target_id") as $r) {
            $inlinks[(int)$r->target_id] = (int)$r->c;
        }
        $impr = [];
        $gt = $wpdb->prefix.'viraseo_gsc_keywords';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$gt}'") === $gt) {
            foreach ($wpdb->get_results("SELECT page_url, SUM(impressions) i FROM {$gt} GROUP BY page_url") as $r) {
                $pid = url_to_postid($r->page_url); if ($pid) $impr[$pid] = (int)$r->i;
            }
        }

        // Greedy assignment: each page joins the highest-DF topic token it contains (once).
        $assigned = []; $out = [];
        foreach (array_keys($candidates) as $topic) {
            $members = [];
            foreach ($posts as $p) {
                if (isset($assigned[$p->ID])) continue;
                if (isset($tokens[$p->ID][$topic])) $members[] = $meta[$p->ID];
            }
            if (count($members) < 2) continue;
            foreach ($members as $m) $assigned[$m['id']] = true;
            usort($members, function($a, $b) use ($inlinks, $impr) {
                $ia = ($inlinks[$a['id']] ?? 0) + ($impr[$a['id']] ?? 0)/100;
                $ib = ($inlinks[$b['id']] ?? 0) + ($impr[$b['id']] ?? 0)/100;
                return ($ib <=> $ia) ?: ($b['len'] <=> $a['len']);
            });
            $pillar = $members[0];
            $pid = $pillar['id'];
            $rest = array_slice($members, 1, 30);

            // How many members already link to the pillar (silo coverage)
            $linked = 0;
            foreach ($rest as $m) if (isset($pairs[$m['id'] . '-' . $pid])) $linked++;
            $coverage = count($rest) ? round($linked * 100 / count($rest)) : 100;
            $clusterImpr = $impr[$pid] ?? 0;
            foreach ($rest as $m) $clusterImpr += ($impr[$m['id']] ?? 0);

            $out[] = [
                'keyword'=>$topic,
                'count'=>count($members),
                'coverage'=>$coverage,
                'impressions'=>PersianText::format_number($clusterImpr),
                'pillar_id'=>$pid,
                'pillar'=>['id'=>$pid, 'title'=>$pillar['title'], 'url'=>get_permalink($pid), 'edit'=>get_edit_post_link($pid,'raw')],
                'members'=>array_map(fn($m)=>[
                    'id'=>$m['id'], 'title'=>$m['title'], 'url'=>get_permalink($m['id']),
                    'linked'=> isset($pairs[$m['id'] . '-' . $pid]),
                ], $rest),
            ];
        }
        usort($out, fn($a, $b) => $b['count'] <=> $a['count']);
        wp_send_json_success(['clusters'=>array_slice($out, 0, 40)]);
    }

    /** Auto-link selected member pages UP to the cluster pillar (build the silo). */
    public function ajax_cluster_link(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;
        $pillar_id = absint($_POST['pillar_id'] ?? 0);
        $members = array_map('absint', (array)($_POST['members'] ?? []));
        if (!$pillar_id || !$members) wp_send_json_error('داده ناقص.');

        $url = get_permalink($pillar_id);
        $anchor = TargetKeywords::get($pillar_id);
        if ($anchor === '') {
            $kw = PersianText::extract_keywords(get_the_title($pillar_id), 1);
            $anchor = $kw ? array_key_first($kw) : get_the_title($pillar_id);
        }
        $linked = 0; $skipped = 0;
        foreach ($members as $mid) {
            if ($mid === $pillar_id) continue;
            $post = get_post($mid);
            if (!$post) { $skipped++; continue; }
            if (strpos($post->post_content, 'href="'.$url.'"') !== false) { $skipped++; continue; }
            $out = $this->insert_link_into_content($post->post_content, $anchor, $url);
            // If anchor not found in body, append a contextual silo link
            if (!$out['inserted']) {
                $content = $post->post_content . "\n\n<p>بیشتر بخوانید: <a href=\"" . esc_url($url) . "\">" . esc_html(get_the_title($pillar_id)) . "</a></p>";
            } else {
                $content = $out['content'];
            }
            wp_update_post(['ID'=>$mid, 'post_content'=>$content]);
            $wpdb->insert($wpdb->prefix.'viraseo_internal_links', ['source_id'=>$mid,'target_id'=>$pillar_id,'anchor'=>mb_substr($anchor,0,500),'link_url'=>$url]);
            $linked++;
        }
        wp_send_json_success(['message'=>sprintf('✅ %d صفحه به ستون خوشه لینک شد. (%d مورد رد/تکراری)', $linked, $skipped)]);
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

        // Pre-compute normalized text + keyword sets + target keyword per post
        $norm = []; $cache = []; $target = [];
        foreach ($posts as $p) {
            $text = wp_strip_all_tags($p->post_content) . ' ' . $p->post_title;
            $norm[$p->ID] = PersianText::normalize(mb_strtolower($text));
            $cache[$p->ID] = PersianText::extract_keywords($text, 25);
            $target[$p->ID] = PersianText::normalize(mb_strtolower(TargetKeywords::get((int)$p->ID)));
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

        // ===== PASS 1 & 2: keyword-targeted linking (EXACT + PARTIAL) =====
        foreach ($posts as $b) {
            if ($count >= 200) break;
            $kw = $target[$b->ID];
            if ($kw === '' || mb_strlen($kw) < 3) continue;
            $kwTokens = array_values(array_filter(PersianText::tokenize($kw), fn($w)=>mb_strlen($w) > 2));
            $tokenCount = count($kwTokens);
            foreach ($posts as $a) {
                if ($count >= 200) break;
                if ($a->ID === $b->ID) continue;
                $content = $norm[$a->ID];

                if (mb_strpos($content, $kw) !== false) {
                    // EXACT: source contains the full target keyword phrase
                    $freq = substr_count($content, $kw);
                    $insert($a->ID, $b->ID, $kw, min(100, 80 + $freq * 4), 'exact', 'تطابق دقیق: متن مبدا شامل عبارت کامل «'.$kw.'» است.');
                } elseif ($tokenCount >= 2) {
                    // PARTIAL: source contains a significant share of the keyword's words
                    $hit = 0;
                    foreach ($kwTokens as $tok) if (mb_strpos($content, $tok) !== false) $hit++;
                    $coverage = $hit / $tokenCount;
                    if ($coverage >= 0.6) {
                        $insert($a->ID, $b->ID, $kw, round(40 + $coverage * 30, 1), 'partial', 'تطابق جزئی: '.$hit.' از '.$tokenCount.' کلمه‌ی عبارت هدف در متن مبدا هست.');
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
