<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\PersianText;

/**
 * Crawl & Host Health [🟢 مستقل]
 *
 * Diagnoses why Google may be crawling your Persian site slowly or incompletely, and
 * finds host-level problems that block efficient crawling. Checks WordPress visibility,
 * robots.txt, XML sitemap reachability, server response time (TTFB), HTTP status,
 * compression, browser caching, and accidental noindex headers — then gives concrete
 * Persian fixes prioritized by impact.
 */
class CrawlHealth {

    public function __construct() {
        add_action('wp_ajax_viraseo_crawl_check', [$this, 'ajax_check']);
    }

    /** A single diagnostic result row. */
    private function check(string $status, string $title, string $detail, string $fix = ''): array {
        // status: ok | warn | bad | info
        return compact('status', 'title', 'detail', 'fix');
    }

    public function ajax_check(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');

        $home = home_url('/');
        $host = wp_parse_url($home, PHP_URL_HOST);
        $checks = [];

        /* ---- 1) WordPress search-engine visibility (the #1 silent killer) ---- */
        if (!get_option('blog_public')) {
            $checks[] = $this->check('bad', 'دیده‌شدن سایت توسط موتورهای جستجو خاموش است',
                'گزینه‌ی «از موتورهای جستجو بخواه این سایت را ایندکس نکنند» در وردپرس فعال است. گوگل کل سایت را نادیده می‌گیرد!',
                'به تنظیمات → خواندن (Reading) بروید و تیک «Discourage search engines» را بردارید.');
        } else {
            $checks[] = $this->check('ok', 'دیده‌شدن سایت برای موتورهای جستجو فعال است', 'وردپرس اجازه‌ی ایندکس را داده است.');
        }

        /* ---- 2) Homepage response: status + TTFB + headers ---- */
        $t0 = microtime(true);
        $resp = wp_remote_get($home, ['timeout' => 20, 'redirection' => 3, 'sslverify' => false, 'user-agent' => 'ViraSEO-CrawlCheck/1.0']);
        $ttfb = (int) round((microtime(true) - $t0) * 1000);

        if (is_wp_error($resp)) {
            $checks[] = $this->check('bad', 'صفحه‌ی اصلی پاسخ نداد',
                'سرور به درخواست خودکار پاسخ نداد: ' . $resp->get_error_message(),
                'ممکن است فایروال/هاست درخواست‌های ربات را مسدود کند. با پشتیبانی هاست بررسی کنید که ربات گوگل (Googlebot) مسدود نباشد.');
        } else {
            $code = (int) wp_remote_retrieve_response_code($resp);
            $headers = wp_remote_retrieve_headers($resp);
            $h = is_object($headers) && method_exists($headers, 'getAll') ? $headers->getAll() : (array) $headers;
            $h = array_change_key_case($h, CASE_LOWER);

            // HTTP status
            if ($code === 200) {
                $checks[] = $this->check('ok', 'وضعیت HTTP صفحه‌ی اصلی: ۲۰۰', 'صفحه‌ی اصلی سالم پاسخ می‌دهد.');
            } else {
                $checks[] = $this->check('bad', 'وضعیت HTTP غیرعادی: ' . $code,
                    'صفحه‌ی اصلی کد ' . $code . ' برگرداند که برای خزش مناسب نیست.',
                    'علت کد ' . $code . ' را بررسی کنید (ریدایرکت زنجیره‌ای، خطای سرور یا دسترسی).');
            }

            // TTFB (host speed for crawl efficiency)
            if ($ttfb <= 600) {
                $checks[] = $this->check('ok', 'زمان پاسخ سرور (TTFB) خوب است: ' . PersianText::format_number($ttfb) . ' میلی‌ثانیه', 'سرعت پاسخ هاست برای خزش مناسب است.');
            } elseif ($ttfb <= 1200) {
                $checks[] = $this->check('warn', 'زمان پاسخ سرور کمی کند است: ' . PersianText::format_number($ttfb) . ' میلی‌ثانیه',
                    'TTFB بالا بودجه‌ی خزش گوگل را کاهش می‌دهد (صفحات کمتری در هر بازدید خزیده می‌شوند).',
                    'کش کامل صفحه فعال کنید (LiteSpeed Cache / WP Rocket) و از افزونه‌های سنگین بکاهید.');
            } else {
                $checks[] = $this->check('bad', 'زمان پاسخ سرور بسیار کند است: ' . PersianText::format_number($ttfb) . ' میلی‌ثانیه',
                    'این معمولاً مشکل هاست است و مستقیماً سرعت و کامل‌بودن خزش گوگل را خراب می‌کند.',
                    'کش صفحه فعال کنید، به هاست قوی‌تر/NVMe یا CDN مهاجرت کنید، و نسخه‌ی PHP را به‌روز کنید.');
            }

            // Compression
            $enc = $h['content-encoding'] ?? '';
            if (stripos((string)$enc, 'gzip') !== false || stripos((string)$enc, 'br') !== false) {
                $checks[] = $this->check('ok', 'فشرده‌سازی خروجی فعال است', 'سرور پاسخ‌ها را با ' . $enc . ' فشرده می‌کند.');
            } else {
                $checks[] = $this->check('warn', 'فشرده‌سازی خروجی (Gzip/Brotli) فعال نیست',
                    'بدون فشرده‌سازی، حجم انتقال بیشتر و خزش کندتر است.',
                    'فشرده‌سازی Gzip یا Brotli را در هاست/افزونه‌ی کش فعال کنید.');
            }

            // Browser caching of the document
            $cc = $h['cache-control'] ?? '';
            if ($cc && stripos((string)$cc, 'no-store') === false && stripos((string)$cc, 'no-cache') === false) {
                $checks[] = $this->check('ok', 'هدر کش (Cache-Control) تنظیم شده است', 'مقدار: ' . (is_array($cc) ? implode(',', $cc) : $cc));
            } else {
                $checks[] = $this->check('info', 'هدر کش مرورگر برای سند HTML تنظیم نشده',
                    'این برای صفحات پویا طبیعی است، اما برای فایل‌های ثابت (CSS/JS/عکس) کش طولانی توصیه می‌شود.',
                    'برای منابع ثابت هدر Cache-Control با عمر طولانی تنظیم کنید.');
            }

            // Accidental noindex via response header
            $xr = $h['x-robots-tag'] ?? '';
            if ($xr && stripos((string)(is_array($xr) ? implode(',', $xr) : $xr), 'noindex') !== false) {
                $checks[] = $this->check('bad', 'هدر X-Robots-Tag دارای noindex است',
                    'سرور در سطح HTTP به گوگل می‌گوید صفحه را ایندکس نکن — این کل ایندکس‌شدن را متوقف می‌کند.',
                    'این هدر را از تنظیمات سرور/افزونه حذف کنید.');
            }
        }

        /* ---- 3) robots.txt ---- */
        $robotsUrl = home_url('/robots.txt');
        $rr = wp_remote_get($robotsUrl, ['timeout' => 12, 'sslverify' => false, 'user-agent' => 'ViraSEO-CrawlCheck/1.0']);
        if (is_wp_error($rr)) {
            $checks[] = $this->check('warn', 'robots.txt در دسترس نیست', 'دریافت robots.txt ناموفق بود.', 'مطمئن شوید ' . $robotsUrl . ' بدون خطا باز می‌شود.');
        } else {
            $rcode = (int) wp_remote_retrieve_response_code($rr);
            $body = (string) wp_remote_retrieve_body($rr);
            if ($rcode >= 400) {
                $checks[] = $this->check('warn', 'robots.txt یافت نشد (HTTP ' . $rcode . ')', 'فایل robots.txt برگردانده نشد.', 'وردپرس به‌صورت پیش‌فرض robots.txt مجازی می‌سازد؛ بررسی کنید چیزی آن را مسدود نکند.');
            } else {
                // Look for blanket disallow of the whole site
                $blocksAll = (bool) preg_match('/^\s*Disallow:\s*\/\s*$/mi', $body);
                $hasSitemap = (bool) preg_match('/^\s*Sitemap:\s*/mi', $body);
                if ($blocksAll) {
                    $checks[] = $this->check('bad', 'robots.txt کل سایت را مسدود کرده است',
                        'یک قانون «Disallow: /» وجود دارد که خزش کل سایت را برای ربات‌ها می‌بندد.',
                        'این خط را از robots.txt حذف کنید مگر اینکه عمداً سایت را بسته باشید.');
                } else {
                    $checks[] = $this->check('ok', 'robots.txt سالم است و کل سایت را نمی‌بندد', 'هیچ قانون «Disallow: /» سراسری یافت نشد.');
                }
                if ($hasSitemap) {
                    $checks[] = $this->check('ok', 'آدرس نقشه‌ی سایت در robots.txt معرفی شده', 'گوگل نقشه‌ی سایت را راحت‌تر پیدا می‌کند.');
                } else {
                    $checks[] = $this->check('info', 'آدرس نقشه‌ی سایت در robots.txt معرفی نشده',
                        'معرفی Sitemap در robots.txt کشف صفحات را سریع‌تر می‌کند.',
                        'خط «Sitemap: ' . home_url('/sitemap_index.xml') . '» را به robots.txt اضافه کنید (Rank Math معمولاً خودکار انجام می‌دهد).');
                }
            }
        }

        /* ---- 4) XML sitemap reachability (Rank Math default) ---- */
        $sitemapCandidates = [home_url('/sitemap_index.xml'), home_url('/sitemap.xml')];
        $sitemapOk = false; $sitemapFound = '';
        foreach ($sitemapCandidates as $sm) {
            $sr = wp_remote_get($sm, ['timeout' => 12, 'sslverify' => false, 'user-agent' => 'ViraSEO-CrawlCheck/1.0']);
            if (!is_wp_error($sr) && (int) wp_remote_retrieve_response_code($sr) === 200) {
                $sitemapOk = true; $sitemapFound = $sm; break;
            }
        }
        if ($sitemapOk) {
            $checks[] = $this->check('ok', 'نقشه‌ی سایت XML در دسترس است', 'یافت شد: ' . $sitemapFound);
        } else {
            $checks[] = $this->check('warn', 'نقشه‌ی سایت XML پیدا نشد',
                'هیچ‌کدام از آدرس‌های رایج نقشه‌ی سایت پاسخ ۲۰۰ ندادند.',
                'در Rank Math ماژول Sitemap را فعال کنید و آدرس نقشه‌ی سایت را در سرچ کنسول ثبت کنید.');
        }

        /* ---- 5) HTTPS ---- */
        if (stripos($home, 'https://') === 0) {
            $checks[] = $this->check('ok', 'سایت روی HTTPS اجرا می‌شود', 'اتصال امن برقرار است.');
        } else {
            $checks[] = $this->check('bad', 'سایت روی HTTPS نیست',
                'HTTPS یک فاکتور رتبه و برای اعتماد کاربر ایرانی ضروری است.',
                'گواهی SSL رایگان (Let’s Encrypt) را از هاست فعال و آدرس سایت را به https تغییر دهید.');
        }

        /* ---- 6) Sample crawl: a few recent posts, measure status + speed ---- */
        $sampleIds = get_posts(['post_type' => TargetKeywords::public_types(), 'post_status' => 'publish', 'numberposts' => 5, 'orderby' => 'modified', 'order' => 'DESC', 'fields' => 'ids']);
        $slow = 0; $errors = 0; $sampleN = 0; $sumTtfb = 0;
        foreach ($sampleIds as $pid) {
            if (TargetKeywords::is_excluded((int)$pid)) continue;
            $u = get_permalink($pid);
            $s0 = microtime(true);
            $sr = wp_remote_head($u, ['timeout' => 15, 'redirection' => 3, 'sslverify' => false, 'user-agent' => 'ViraSEO-CrawlCheck/1.0']);
            $ms = (int) round((microtime(true) - $s0) * 1000);
            $sampleN++; $sumTtfb += $ms;
            if (is_wp_error($sr)) { $errors++; continue; }
            $sc = (int) wp_remote_retrieve_response_code($sr);
            if ($sc >= 400) $errors++;
            if ($ms > 1200) $slow++;
        }
        if ($sampleN > 0) {
            $avg = (int) round($sumTtfb / $sampleN);
            if ($errors > 0) {
                $checks[] = $this->check('bad', $errors . ' صفحه از ' . $sampleN . ' صفحه‌ی نمونه خطا داد',
                    'برخی صفحات منتشرشده به‌درستی پاسخ نمی‌دهند که خزش را مختل می‌کند.',
                    'این صفحات را دستی باز کنید و خطای سرور/ریدایرکت را رفع کنید.');
            } elseif ($slow > 0) {
                $checks[] = $this->check('warn', $slow . ' صفحه از ' . $sampleN . ' صفحه‌ی نمونه کند بودند (میانگین ' . PersianText::format_number($avg) . ' ms)',
                    'کندی صفحات داخلی هم بودجه‌ی خزش را مصرف می‌کند.',
                    'کش صفحه و بهینه‌سازی کوئری دیتابیس را در نظر بگیرید.');
            } else {
                $checks[] = $this->check('ok', 'صفحات نمونه سالم و سریع بودند (میانگین ' . PersianText::format_number($avg) . ' ms)', $sampleN . ' صفحه بررسی شد.');
            }
        }

        // Score: start 100, subtract per issue weight
        $score = 100; $bad = 0; $warn = 0;
        foreach ($checks as $c) {
            if ($c['status'] === 'bad') { $score -= 18; $bad++; }
            elseif ($c['status'] === 'warn') { $score -= 7; $warn++; }
        }
        $score = max(5, min(100, $score));

        update_option('viraseo_crawl_last', ['bad' => $bad, 'warn' => $warn, 'score' => $score, 'at' => current_time('mysql')], false);

        wp_send_json_success([
            'checks' => $checks,
            'score' => $score,
            'bad' => $bad,
            'warn' => $warn,
            'ttfb' => PersianText::format_number($ttfb),
        ]);
    }

    /** Used by ActionPlan: count of serious crawl issues from last run (cached). */
    public static function issue_count(): int {
        $c = get_option('viraseo_crawl_last', []);
        return is_array($c) ? (int)($c['bad'] ?? 0) : 0;
    }
}
