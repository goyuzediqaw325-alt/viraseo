<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\{JalaliDate, PersianText};

/** Feature 4: Backlink CRM + Disavow [🟢 مستقل] */
class BacklinkCRM {
    public function __construct() {
        add_action('wp_ajax_viraseo_get_backlinks', [$this, 'ajax_list']);
        add_action('wp_ajax_viraseo_add_backlink', [$this, 'ajax_add']);
        add_action('wp_ajax_viraseo_del_backlink', [$this, 'ajax_del']);
        add_action('wp_ajax_viraseo_get_disavow', [$this, 'ajax_disavow']);
        add_action('wp_ajax_viraseo_add_disavow', [$this, 'ajax_add_disavow']);
        add_action('wp_ajax_viraseo_gen_disavow', [$this, 'ajax_gen_disavow']);
        add_action('wp_ajax_viraseo_bl_stats', [$this, 'ajax_stats']);
        add_action('wp_ajax_viraseo_bl_import_gsc', [$this, 'ajax_import_gsc']);
    }

    /**
     * Import backlinks from a Google Search Console "Links" export (CSV).
     * The GSC API has NO Links endpoint, so users export the report from the GSC UI
     * (Links > Top linking sites / Top linked pages) and paste/upload the CSV here.
     * Parser is language-agnostic: it reads the first column as the source domain/URL.
     */
    public function ajax_import_gsc(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        global $wpdb;

        $csv = (string) wp_unslash($_POST['csv'] ?? '');
        if (trim($csv) === '') wp_send_json_error('محتوای CSV خالی است.');

        $target = esc_url_raw($_POST['target_url'] ?? '') ?: get_site_url();
        $t = $wpdb->prefix.'viraseo_backlinks';
        $lines = preg_split('/\r\n|\r|\n/', $csv);

        $imported = 0; $skipped = 0; $jalali = JalaliDate::now()['date'];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Detect delimiter (GSC CSV = comma; sheets paste = tab)
            $delim = (strpos($line, "\t") !== false) ? "\t" : ',';
            $cols = str_getcsv($line, $delim);
            $first = trim($cols[0] ?? '', " \"'");
            if ($first === '') { $skipped++; continue; }

            // Build a host from the first column (domain or full URL)
            $url = (preg_match('#^https?://#i', $first)) ? $first : 'http://' . $first;
            $host = strtolower(preg_replace('/^www\./i', '', (string) wp_parse_url($url, PHP_URL_HOST)));
            // Skip header rows / non-domain values (must contain a dot, no spaces)
            if (!$host || strpos($host, '.') === false || preg_match('/\s/', $host)) { $skipped++; continue; }

            // Optional 2nd column = number of linking pages (kept in notes)
            $count = isset($cols[1]) ? preg_replace('/[^\d]/', '', $cols[1]) : '';
            $note = 'وارد شده از سرچ کنسول' . ($count !== '' ? ' — ' . $count . ' صفحه لینک‌دهنده' : '');

            // De-dup by source_domain + target
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE source_domain=%s AND target_url=%s", $host, $target))) {
                $skipped++; continue;
            }
            $ok = $wpdb->insert($t, [
                'source_url'=>esc_url_raw($url), 'source_domain'=>$host,
                'target_url'=>$target, 'anchor'=>'', 'link_type'=>'other',
                'cost'=>0, 'dofollow'=>1, 'da'=>0, 'spam_score'=>0,
                'link_status'=>'live', 'date_acquired'=>current_time('Y-m-d'),
                'date_jalali'=>$jalali, 'notes'=>$note, 'created_by'=>get_current_user_id(),
            ]);
            if ($ok !== false) $imported++; else $skipped++;
        }

        wp_send_json_success([
            'message'=> sprintf('✅ %d بک‌لینک از سرچ کنسول وارد شد. (%d مورد رد/تکراری)', $imported, $skipped),
            'imported'=>$imported, 'skipped'=>$skipped,
        ]);
    }

    public function ajax_list(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_backlinks ORDER BY created_at DESC LIMIT 50");
        $data = array_map(fn($r)=>[
            'id'=>$r->id,'domain'=>$r->source_domain,'anchor'=>$r->anchor,
            'type'=>$r->link_type,'da'=>$r->da,'cost'=>PersianText::format_number($r->cost),
            'status'=>$r->link_status,'dofollow'=>(bool)$r->dofollow,
            'date'=>$r->date_jalali?:JalaliDate::format($r->date_acquired??$r->created_at),
        ], $rows);
        wp_send_json_success(['rows'=>$data]);
    }

    public function ajax_add(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $src = esc_url_raw($_POST['source_url']??'');
        $tgt = esc_url_raw($_POST['target_url']??'');
        if (!$src||!$tgt) wp_send_json_error('آدرس‌ها الزامی.');

        $jalali = sanitize_text_field($_POST['date_jalali']??'');
        $greg = $jalali ? JalaliDate::jalali_to_gregorian_str($jalali) : current_time('Y-m-d');
        if (!$jalali) { $j=JalaliDate::now(); $jalali=$j['date']; }

        $wpdb->insert($wpdb->prefix.'viraseo_backlinks', [
            'source_url'=>$src,'source_domain'=>wp_parse_url($src,PHP_URL_HOST)?:'',
            'target_url'=>$tgt,'anchor'=>sanitize_text_field($_POST['anchor']??''),
            'link_type'=>sanitize_text_field($_POST['type']??'other'),
            'cost'=>absint($_POST['cost']??0),'dofollow'=>!empty($_POST['dofollow'])?1:0,
            'da'=>min(100,absint($_POST['da']??0)),'spam_score'=>min(100,absint($_POST['spam']??0)),
            'link_status'=>sanitize_text_field($_POST['status']??'pending'),
            'date_acquired'=>$greg,'date_jalali'=>$jalali,
            'contact'=>sanitize_text_field($_POST['contact']??''),
            'notes'=>sanitize_textarea_field($_POST['notes']??''),
            'created_by'=>get_current_user_id(),
        ]);
        wp_send_json_success(['id'=>$wpdb->insert_id]);
    }

    public function ajax_del(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $id = absint($_POST['id']??0);
        if ($id) $wpdb->delete($wpdb->prefix.'viraseo_backlinks', ['id'=>$id]);
        wp_send_json_success();
    }

    public function ajax_disavow(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_disavow ORDER BY added_at DESC");
        wp_send_json_success(['rows'=>array_map(fn($r)=>['id'=>$r->id,'entry'=>$r->entry,'type'=>$r->entry_type,'reason'=>$r->reason], $rows)]);
    }

    public function ajax_add_disavow(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $val = sanitize_text_field($_POST['entry']??'');
        $type = sanitize_text_field($_POST['type']??'domain');
        if (!$val) wp_send_json_error('مقدار الزامی.');
        if ($type==='domain') $val = preg_replace('#^https?://(www\.)?#','',rtrim($val,'/'));
        $wpdb->insert($wpdb->prefix.'viraseo_disavow', ['entry'=>$val,'entry_type'=>$type,'reason'=>sanitize_text_field($_POST['reason']??'')]);
        wp_send_json_success();
    }

    public function ajax_gen_disavow(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}viraseo_disavow ORDER BY entry_type,entry");
        if (!$rows) wp_send_json_error('لیست خالی.');
        $lines = ["# Disavow file - ViraSEO - ".JalaliDate::now_str(),""];
        foreach ($rows as $r) {
            if ($r->reason) $lines[] = "# {$r->reason}";
            $lines[] = ($r->entry_type==='domain'?'domain:':'').$r->entry;
        }
        $content = implode("\n",$lines)."\n";
        $up = wp_upload_dir();
        file_put_contents($up['basedir'].'/viraseo-disavow.txt', $content);
        wp_send_json_success(['content'=>$content,'url'=>$up['baseurl'].'/viraseo-disavow.txt']);
    }

    public function ajax_stats(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_backlinks';
        wp_send_json_success([
            'total'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t}"),
            'live'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE link_status='live'"),
            'dead'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE link_status='dead'"),
            'invest'=>PersianText::format_number($wpdb->get_var("SELECT COALESCE(SUM(cost),0) FROM {$t}")),
            'avg_da'=>JalaliDate::to_fa(number_format((float)$wpdb->get_var("SELECT AVG(da) FROM {$t} WHERE da>0"),1)),
        ]);
    }
}
