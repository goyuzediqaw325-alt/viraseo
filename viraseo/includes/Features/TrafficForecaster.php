<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Admin\Dashboard;
use ViraSEO\Api\AiClient;
use ViraSEO\Utils\{JalaliDate, PersianText};

/** Feature 8: Traffic ROI Forecaster [🟢 مستقل] */
class TrafficForecaster {
    private const CTR = [1=>31.7,2=>24.7,3=>18.6,4=>13.2,5=>9.5,6=>6.3,7=>4.4,8=>3.3,9=>2.8,10=>2.5,11=>2.2,12=>1.9,13=>1.6,14=>1.4,15=>1.2,16=>1.0,17=>0.9,18=>0.8,19=>0.7,20=>0.6];

    public function __construct() {
        add_action('wp_ajax_viraseo_forecast', [$this, 'ajax']);
        add_action('wp_ajax_viraseo_forecast_page', [$this, 'ajax_page']);
        add_action('wp_ajax_viraseo_forecast_ai', [$this, 'ajax_ai_strategy']);
        add_action('wp_ajax_viraseo_forecast_autofix', [$this, 'ajax_autofix']);
        add_action('wp_ajax_viraseo_forecast_apply', [$this, 'ajax_apply']);
    }

    /** Action recommendation based on current position. */
    private function action_for(float $pos): array {
        if ($pos <= 3) return ['برای صفحه اول رقابت کنید: بهبود CTR با عنوان جذاب‌تر، افزودن FAQ Schema و تصاویر بهینه.', 'green'];
        if ($pos <= 10) return ['در صفحه اول هستید: عنوان و متای جذاب‌تر برای کلیک بیشتر + هدف‌گیری Featured Snippet (پاسخ کوتاه به سوال).', 'green'];
        if ($pos <= 20) return ['نزدیک صفحه اول: محتوا را عمیق‌تر کنید (H2/H3، پاسخ به سوالات)، و لینک داخلی بیشتری به این صفحه بدهید.', 'orange'];
        return ['عقب‌تر: محتوا را کامل بازنویسی کنید، کلمه هدف را شفاف کنید و چند لینک داخلی قوی بسازید.', 'red'];
    }

    public function ajax(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_gsc_keywords';
        $target = max(1,min(20,absint($_POST['target']??3)));
        $target_ctr = (self::CTR[$target]??0.6) / 100;

        // Get keywords ranked 4-30 (page 1 bottom to page 3) with impressions
        // These are the "opportunity" keywords — ranking but not winning
        $rows = $wpdb->get_results(
            "SELECT keyword, page_url, SUM(clicks) c, SUM(impressions) i, AVG(position) p
             FROM {$t}
             WHERE position BETWEEN 4 AND 30 AND impressions >= 5
             GROUP BY keyword_hash, page_url_hash
             ORDER BY i DESC LIMIT 150"
        );

        $data = [];
        foreach ($rows as $r) {
            $curPos = round($r->p, 1);
            $curClicks = (int)$r->c;
            $impr = (int)$r->i;

            // Potential at target rank
            $potential = (int)round($impr * $target_ctr);
            $growth = max(0, $potential - $curClicks);

            // Effort estimate: how far to climb
            $gap = $curPos - $target;
            $effort = $gap <= 2 ? 'آسان' : ($gap <= 5 ? 'متوسط' : 'سخت');
            $effortColor = $gap <= 2 ? 'green' : ($gap <= 5 ? 'orange' : 'red');

            // Priority: high growth + low effort = high priority
            $priority = $growth / max(1, $gap);

            $data[] = [
                'keyword'=>$r->keyword,'url'=>$r->page_url,
                'position'=>JalaliDate::to_fa(number_format($curPos,1)),
                'position_raw'=>$curPos,
                'impressions'=>PersianText::format_number($impr),
                'clicks'=>PersianText::format_number($curClicks),
                'potential'=>PersianText::format_number($potential),
                'growth'=>'+'.PersianText::format_number($growth),
                'growth_raw'=>$growth,
                'effort'=>$effort,
                'effort_color'=>$effortColor,
                'priority'=>$priority,
                'action'=>$this->action_for($curPos)[0],
            ];
        }

        // Sort by priority (best opportunities first)
        usort($data, fn($a,$b)=>$b['priority']<=>$a['priority']);
        $data = array_slice($data, 0, 300);

        wp_send_json_success([
            'rows'=>$data,
            'target'=>$target,
            'target_ctr'=>self::CTR[$target].'%',
            'total_growth'=>PersianText::format_number(array_sum(array_column($data,'growth_raw'))),
            'count'=>count($data),
        ]);
    }

    /**
     * Per-page traffic opportunities: ALL queries a page ranks for + actionable suggestions.
     * Surfaces "other keywords" you can target on the same page to grow its traffic.
     */
    public function ajax_page(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_gsc_keywords';
        $url = esc_url_raw($_POST['url'] ?? '');
        if (!$url) wp_send_json_error('آدرس صفحه نامعتبر است.');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT keyword, SUM(clicks) c, SUM(impressions) i, AVG(position) p
             FROM {$t} WHERE page_url=%s GROUP BY keyword_hash ORDER BY i DESC LIMIT 100", $url
        ));
        if (!$rows) wp_send_json_error('برای این صفحه داده‌ای در سرچ کنسول نیست.');

        $kws = [];
        // Categorized opportunity buckets (advanced, data-driven strategy)
        $striking = [];   // pos 11-20: closest to page-1 breakthrough
        $quickWin = [];    // pos 4-10: page-1 bottom, easiest clicks
        $ctrGap = [];      // page-1 but CTR far below expected = title/meta problem
        $totalImpr = 0; $totalClicks = 0;
        foreach ($rows as $r) {
            $pos = round((float)$r->p, 1);
            $impr = (int)$r->i; $clk = (int)$r->c;
            $totalImpr += $impr; $totalClicks += $clk;
            $kws[] = [
                'keyword'=>$r->keyword,
                'position'=>JalaliDate::to_fa(number_format($pos,1)),
                'impressions'=>PersianText::format_number($impr),
                'clicks'=>PersianText::format_number($clk),
                'pos_raw'=>$pos,
                'is_opportunity'=> ($pos > 3 && $impr >= 10),
            ];
            if ($impr < 10) continue;
            if ($pos >= 11 && $pos <= 20) $striking[] = $r;
            elseif ($pos >= 4 && $pos <= 10) $quickWin[] = $r;
            if ($pos <= 10 && $impr >= 50) {
                $expCtr = (self::CTR[(int)round($pos)] ?? 2.5) / 100;
                $actualCtr = $impr > 0 ? $clk / $impr : 0;
                if ($actualCtr < $expCtr * 0.5) $ctrGap[] = $r; // getting <50% of expected clicks
            }
        }
        usort($striking, fn($a,$b)=>(int)$b->i <=> (int)$a->i);
        usort($quickWin, fn($a,$b)=>(int)$b->i <=> (int)$a->i);
        usort($ctrGap, fn($a,$b)=>(int)$b->i <=> (int)$a->i);

        $best = $rows[0];
        [$rec] = $this->action_for(round((float)$best->p, 1));

        // Build a prioritized, data-driven strategy (not generic advice)
        $strategy = [];
        if ($quickWin) {
            $names = implode('، ', array_slice(array_map(fn($r)=>'«'.$r->keyword.'»', $quickWin), 0, 4));
            $strategy[] = ['icon'=>'🎯','label'=>'بُردِ سریع (جایگاه ۴ تا ۱۰)',
                'text'=>'این کلمات همین حالا در صفحه اول هستند: '.$names.'. با تقویت عنوان و افزودن یک پاراگراف پاسخ مستقیم، سریع‌ترین رشد کلیک را می‌گیرید.'];
        }
        if ($striking) {
            $names = implode('، ', array_slice(array_map(fn($r)=>'«'.$r->keyword.'»', $striking), 0, 4));
            $strategy[] = ['icon'=>'🚀','label'=>'فاصله‌ی ضربه (جایگاه ۱۱ تا ۲۰)',
                'text'=>'این کلمات یک قدم تا صفحه اول فاصله دارند: '.$names.'. یک زیربخش H2 اختصاصی برای هرکدام بنویسید و ۲ تا ۳ لینک داخلی با همین انکرها به این صفحه بدهید.'];
        }
        if ($ctrGap) {
            $names = implode('، ', array_slice(array_map(fn($r)=>'«'.$r->keyword.'»', $ctrGap), 0, 4));
            $strategy[] = ['icon'=>'🖱️','label'=>'نشتی نرخ کلیک (CTR پایین)',
                'text'=>'برای این کلمات جایگاه خوب دارید اما کلیک کم می‌گیرید: '.$names.'. عنوان و متادیسکریپشن را جذاب‌تر و با عدد/سال بنویسید تا کلیک‌ها چند برابر شود.'];
        }
        $strategy[] = ['icon'=>'❓','label'=>'هدف‌گیری اسنیپت و FAQ',
            'text'=>'یک بخش «سوالات متداول» با کلماتی که جایگاه ۸ تا ۲۰ دارند اضافه کنید و به هر سوال پاسخ کوتاه ۴۰ تا ۶۰ کلمه‌ای بدهید تا شانس Featured Snippet بالا برود.'];
        $strategy[] = ['icon'=>'🔗','label'=>'تقویت لینک داخلی',
            'text'=>'از صفحات مرتبط دیگر با انکرتکست همین کلمات به این صفحه لینک داخلی بدهید تا قدرت لینک و رتبه افزایش یابد.'];

        // Legacy flat checklist (kept for backward compatibility with older UI)
        $checklist = array_map(fn($s)=>$s['text'], $strategy);
        array_unshift($checklist, $rec);

        wp_send_json_success([
            'keywords'=>$kws,
            'checklist'=>$checklist,
            'strategy'=>$strategy,
            'count'=>count($kws),
            'summary'=>[
                'impressions'=>PersianText::format_number($totalImpr),
                'clicks'=>PersianText::format_number($totalClicks),
                'ctr'=>$totalImpr>0 ? JalaliDate::to_fa(number_format($totalClicks/$totalImpr*100,1)).'٪' : '۰٪',
                'striking'=>PersianText::format_number(count($striking)),
                'quickwin'=>PersianText::format_number(count($quickWin)),
                'ctrgap'=>PersianText::format_number(count($ctrGap)),
            ],
            'ai_enabled'=>AiClient::is_enabled(),
            'url'=>$url,
        ]);
    }

    /**
     * AI-powered complete traffic-increase strategy for a single page, grounded in its real GSC data.
     * Builds a rich prompt from the page's actual keywords/positions/impressions/CTR.
     */
    public function ajax_ai_strategy(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');
        global $wpdb;
        $t = $wpdb->prefix.'viraseo_gsc_keywords';
        $url = esc_url_raw($_POST['url'] ?? '');
        if (!$url) wp_send_json_error('آدرس صفحه نامعتبر است.');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT keyword, SUM(clicks) c, SUM(impressions) i, AVG(position) p
             FROM {$t} WHERE page_url=%s GROUP BY keyword_hash ORDER BY i DESC LIMIT 40", $url
        ));
        if (!$rows) wp_send_json_error('برای این صفحه داده‌ای در سرچ کنسول نیست.');

        // Resolve the post for title + target keyword context
        $pid = url_to_postid($url);
        $title = $pid ? get_the_title($pid) : $url;
        $target = $pid ? TargetKeywords::get($pid) : '';

        $lines = '';
        foreach ($rows as $r) {
            $pos = round((float)$r->p, 1);
            $impr = (int)$r->i; $clk = (int)$r->c;
            $ctr = $impr > 0 ? round($clk/$impr*100, 1) : 0;
            $lines .= "- «{$r->keyword}» | جایگاه {$pos} | نمایش {$impr} | کلیک {$clk} | CTR {$ctr}٪\n";
        }

        $system = 'شما استراتژیست ارشد سئوی فارسی هستید و اصول Helpful Content و E-E-A-T گوگل را کامل می‌شناسید. '
                . 'بر اساس داده‌های واقعی سرچ کنسول، یک نقشه‌ی راه عملی و دقیق فقط به فارسی و کاملاً ساختارمند با تیتر بده.';
        $user = "صفحه: {$title}\nآدرس: {$url}\n" . ($target ? "کلمه هدف فعلی: «{$target}»\n" : '')
              . "\nداده‌های واقعی این صفحه در سرچ کنسول گوگل:\n{$lines}\n"
              . "یک استراتژی کامل افزایش ترافیک برای همین صفحه بده شامل:\n"
              . "۱) کلماتی که با کمترین تلاش بیشترین کلیک را می‌آورند (بُرد سریع) و دقیقاً چه کنم\n"
              . "۲) کلمات «فاصله‌ی ضربه» (جایگاه ۱۱ تا ۲۰) و نقشه‌ی رساندن آن‌ها به صفحه اول\n"
              . "۳) کلماتی که جایگاه خوب اما CTR پایین دارند: پیشنهاد عنوان و متادیسکریپشن جدید و جذاب\n"
              . "۴) ساختار هدینگ پیشنهادی (H2/H3) و بخش‌هایی که باید به محتوا اضافه شوند\n"
              . "۵) ۵ تا ۸ سوال متداول برای هدف‌گیری اسنیپت\n"
              . "۶) پیشنهاد لینک داخلی (انکرتکست و صفحات مبدأ)\n"
              . "۷) اولویت‌بندی اقدامات از نظر «بیشترین اثر با کمترین تلاش».";

        $res = AiClient::chat($system, $user, 0.5);
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success(['text'=>$res['text'], 'cost'=>$res['cost'], 'tokens'=>$res['tokens']]);
    }

    /**
     * Auto-fix page content for traffic increase. Reads real GSC data for the page,
     * gets current post content, asks AI to produce an optimized version of specific
     * sections (H2s, paragraphs to add/edit) targeting opportunity keywords.
     * Returns both old and proposed new content for user diff + approval.
     */
    public function ajax_autofix(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        if (!AiClient::is_enabled()) wp_send_json_error('هوش مصنوعی فعال نیست. در تنظیمات فعال کنید.');
        global $wpdb;
        $t = $wpdb->prefix . 'viraseo_gsc_keywords';
        $url = esc_url_raw($_POST['url'] ?? '');
        $pid = $url ? url_to_postid($url) : 0;
        if (!$pid) wp_send_json_error('صفحه‌ی وردپرسی برای این آدرس یافت نشد. فقط پست‌ها/صفحات/محصولات قابل اصلاح هستند.');

        $post = get_post($pid);
        if (!$post) wp_send_json_error('پست یافت نشد.');

        // GSC data for this page
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT keyword, SUM(clicks) c, SUM(impressions) i, AVG(position) p
             FROM {$t} WHERE page_url=%s GROUP BY keyword_hash ORDER BY i DESC LIMIT 30", $url
        ));
        if (!$rows) wp_send_json_error('برای این صفحه داده‌ای در سرچ کنسول نیست.');

        $target = TargetKeywords::get($pid);
        $secondary = TargetKeywords::get_secondary($pid);
        $title = get_the_title($pid);
        $content = $post->post_content;
        // Truncate content for AI context (keep first ~1500 words)
        $words = preg_split('/\s+/u', wp_strip_all_tags(strip_shortcodes($content)));
        $contentPreview = implode(' ', array_slice($words, 0, 800));

        $kwLines = '';
        foreach ($rows as $r) {
            $pos = round((float)$r->p, 1);
            $imp = (int)$r->i;
            $kwLines .= "- «{$r->keyword}» | جایگاه {$pos} | نمایش {$imp}\n";
        }

        $system = 'شما ویراستار ارشد محتوای سئوی فارسی هستید و اصول Helpful Content و E-E-A-T گوگل را کامل می‌شناسید. '
                . 'وظیفه‌ی شما: بهبود یک صفحه‌ی موجود برای افزایش ترافیک واقعی بر اساس داده‌های سرچ کنسول. '
                . 'محتوای فعلی را ویرایش/بازنویسی/تکمیل کن — نه از اول بنویس. بخش‌های مفید را نگه دار، ایرادها را رفع کن، بخش‌های جدید مفید اضافه کن. '
                . 'خروجی باید HTML (heading, p, ul, table) و کاملاً فارسی باشد. '
                . 'مهم: فقط محتوای نهایی HTML را برگردان. هیچ توضیح، یادداشت، دستورالعمل یا متن خارج از محتوا ننویس. هیچ جمله‌ای مثل «این صفحه شامل...» یا «توضیح:» ننویس.';

        $user = "عنوان صفحه: {$title}\nآدرس: {$url}\n"
              . ($target ? "کلمه هدف اصلی: «{$target}»\n" : '')
              . ($secondary ? "کلمات فرعی: " . implode('، ', $secondary) . "\n" : '')
              . "\nکلمات کلیدی واقعی این صفحه در سرچ کنسول (فرصت‌های رشد):\n{$kwLines}\n"
              . "خلاصه‌ی محتوای فعلی (اول ۱۵۰۰ کلمه):\n{$contentPreview}\n\n"
              . "حالا محتوای بهبودیافته را بنویس. قوانین:\n"
              . "۱) کلمات «فاصله‌ی ضربه» (جایگاه ۱۱-۲۰ با نمایش بالا) را حتماً در زیربخش‌های H2/H3 جدید پوشش بده\n"
              . "۲) کلماتی که جایگاه ۴-۱۰ دارند را در پاراگراف‌های اول صفحه تقویت کن\n"
              . "۳) یک بخش «سوالات متداول» با ۴-۶ سوال واقعی مرتبط اضافه کن\n"
              . "۴) جدول یا لیست مقایسه‌ای اضافه کن اگر به موضوع می‌خورد\n"
              . "۵) محتوا را انسانی، مفید و ارزشمند بنویس (نه کلیدواژه‌چینی)\n"
              . "۶) طول نهایی حداقل ۱.۵ برابر طول فعلی باشد\n"
              . "کل محتوای بهبودیافته (HTML) را برگردان.";

        $res = AiClient::chat($system, $user, 0.5, 8000);
        if (isset($res['error'])) wp_send_json_error($res['error']);

        // Clean AI response: strip any leading meta-commentary before actual HTML
        $text = $res['text'];
        // If AI started with plain-text lines before the first HTML tag, strip them
        if (preg_match('/^(.*?)(<(?:h[1-6]|p|div|ul|ol|table|section|article)[>\s])/uis', $text, $m) && strlen(trim($m[1])) > 0) {
            $text = substr($text, strlen($m[1]));
        }
        // Strip trailing meta-commentary after last closing HTML tag
        $text = preg_replace('/(<\/(?:p|div|ul|ol|table|section|article|h[1-6])>)\s*[^<]+$/uis', '$1', $text);

        // Save the proposed content temporarily in post meta for the apply step
        update_post_meta($pid, '_viraseo_proposed_content', $text);

        wp_send_json_success([
            'post_id'     => $pid,
            'title'       => $title,
            'old_content' => $content,
            'new_content' => $text,
            'cost'        => $res['cost'],
            'tokens'      => $res['tokens'],
        ]);
    }

    /**
     * Apply the AI-proposed content to the post (user confirmed).
     * POST: post_id, content (the final content — user may have manually edited it).
     */
    public function ajax_apply(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        $pid = absint($_POST['post_id'] ?? 0);
        $content = wp_kses_post($_POST['content'] ?? '');
        if (!$pid || !$content) wp_send_json_error('داده‌ی نامعتبر.');
        $post = get_post($pid);
        if (!$post) wp_send_json_error('پست یافت نشد.');

        // Save a backup of the old content
        update_post_meta($pid, '_viraseo_content_backup', $post->post_content);
        update_post_meta($pid, '_viraseo_content_backup_time', current_time('mysql'));

        wp_update_post(['ID' => $pid, 'post_content' => $content]);
        delete_post_meta($pid, '_viraseo_proposed_content');

        wp_send_json_success(['message' => '✅ محتوای بهبودیافته ذخیره شد. نسخه‌ی قبلی به‌عنوان بکاپ نگه داشته شد.']);
    }
}
