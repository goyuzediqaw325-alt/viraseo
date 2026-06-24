<?php defined('ABSPATH') || exit; ?>
<?php $s = \ViraSEO\Admin\Dashboard::get(); ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">تنظیمات</h1>
  </div>
  <form method="post" action="options.php" class="vs-card">
    <?php settings_fields('viraseo_opts'); ?>
    <h3 class="vs-card-title">اتصال به سرچ کنسول (OAuth Proxy)</h3>
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span><p>آدرس Cloudflare Worker خود را وارد کنید. کاربران فقط دکمه «اتصال به گوگل» می‌زنن — نیازی به Client ID ندارن.</p></div>
    <div class="vs-field"><label class="vs-label">آدرس OAuth Proxy (Cloudflare Worker)</label><input class="vs-input vs-input-ltr" name="viraseo_settings[oauth_proxy_url]" value="<?php echo esc_attr($s['oauth_proxy_url'] ?? ''); ?>" placeholder="https://viraseo-auth.your-account.workers.dev"><span class="vs-hint">راهنمای نصب Worker در پوشه oauth-proxy/ موجود است</span></div>
    <h3 class="vs-card-title">n8n</h3>
    <div class="vs-field"><label class="vs-label">آدرس n8n</label><input class="vs-input vs-input-ltr" name="viraseo_settings[n8n_url]" value="<?php echo esc_attr($s['n8n_url'] ?? ''); ?>" placeholder="https://n8n.example.com"></div>
    <div class="vs-field"><label class="vs-label">Secret Webhook</label><input class="vs-input vs-input-ltr" name="viraseo_settings[n8n_secret]" value="<?php echo esc_attr($s['n8n_secret'] ?? ''); ?>"></div>
    <h3 class="vs-card-title">کلید Serper API (برای تحلیل SERP)</h3>
    <div class="vs-field"><label class="vs-label">حالت تحلیل SERP (۱۰ نتیجه)</label><select class="vs-input" name="viraseo_settings[serp_mode]" style="max-width:300px"><option value="direct" <?php selected(($s['serp_mode'] ?? 'direct'), 'direct'); ?>>مستقیم از Serper (بدون n8n — پیشنهادی)</option><option value="n8n" <?php selected(($s['serp_mode'] ?? 'direct'), 'n8n'); ?>>از طریق n8n</option></select><span class="vs-hint">حالت مستقیم: افزونه خودش Serper را صدا می‌زند. حالت n8n: ورکفلوی n8n نتایج را پردازش و برمی‌گرداند.</span></div>
    <div class="vs-field"><label class="vs-label">حالت تحلیل دقیق (اینسپکتور)</label><select class="vs-input" name="viraseo_settings[inspect_mode]" style="max-width:300px"><option value="direct" <?php selected(($s['inspect_mode'] ?? 'direct'), 'direct'); ?>>مستقیم از PHP (بدون n8n)</option><option value="n8n" <?php selected(($s['inspect_mode'] ?? 'direct'), 'n8n'); ?>>از طریق n8n (پیشرفته‌تر + Browserless)</option></select><span class="vs-hint">حالت n8n: از headless Chrome (Browserless) برای صفحات JS استفاده می‌کند و تحلیل کامل‌تری دارد.</span></div>
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span><p>گوگل اسکرپ مستقیم را از سرور مسدود می‌کند. برای تحلیل رقبا از سرویس رایگان <a href="https://serper.dev" target="_blank">Serper.dev</a> استفاده می‌کنیم (۲۵۰۰ جستجوی رایگان). ثبت‌نام کنید، کلید API را کپی و اینجا وارد کنید. این کلید به‌صورت امن به n8n ارسال می‌شود.</p></div>
    <div class="vs-field"><label class="vs-label">کلید Serper API</label><input class="vs-input vs-input-ltr" name="viraseo_settings[serper_api_key]" value="<?php echo esc_attr($s['serper_api_key'] ?? ''); ?>" placeholder="مثلا: 0a1b2c3d4e5f..."><span class="vs-hint">از <a href="https://serper.dev/api-key" target="_blank">serper.dev/api-key</a> دریافت کنید</span></div>

    <div class="vs-field"><label class="vs-label">توکن Browserless (اختیاری — برای تحلیل دقیق صفحات JS)</label><input class="vs-input vs-input-ltr" name="viraseo_settings[browserless_token]" value="<?php echo esc_attr($s['browserless_token'] ?? ''); ?>" placeholder="your-browserless-token"><span class="vs-hint">از <a href="https://browserless.io" target="_blank">browserless.io</a> رایگان (۱۰۰۰ درخواست/ماه). بدون این، صفحات JS-rendered (React/Next.js) قابل تحلیل کامل نیستند.</span></div>
    <h3 class="vs-card-title">⚡ کلید PageSpeed Insights (Core Web Vitals)</h3>
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span><p>بخش «سرعت سایت» بدون کلید هم کار می‌کند، اما گوگل نرخ درخواست بدون کلید را محدود می‌کند. برای بررسی دسته‌ای بدون خطا، یک کلید رایگان از <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank">Google Cloud Console (PageSpeed Insights API)</a> بسازید و اینجا وارد کنید.</p></div>
    <div class="vs-field"><label class="vs-label">کلید PageSpeed Insights API</label><input class="vs-input vs-input-ltr" name="viraseo_settings[psi_api_key]" value="<?php echo esc_attr($s['psi_api_key'] ?? ''); ?>" placeholder="AIza..."><span class="vs-hint">اختیاری — فقط برای رفع محدودیت نرخ در بررسی دسته‌ای</span></div>
    <div class="vs-field"><label class="vs-label"><input type="checkbox" name="viraseo_settings[psi_use_proxy]" value="1" <?php checked(!empty($s['psi_use_proxy'])); ?>> ارتباط با PageSpeed از طریق پروکسی cURL</label><span class="vs-hint">اگر هاست ایران به Google PageSpeed دسترسی ندارد، این گزینه را فعال کنید تا از همان پروکسی cURL تنظیم‌شده استفاده شود.</span></div>

    <h3 class="vs-card-title">📋 نوع صفحات مورد تحلیل</h3>
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span><p>فقط نوع صفحاتی که انتخاب کنید در تمام بخش‌های افزونه (آمادگی AI، محتوای کهنه، On-Page، لینک‌سازی، پیش‌بینی و...) تحلیل و نمایش داده می‌شوند. اگر هیچ‌کدام انتخاب نشود، همه نوع‌های عمومی نمایش داده می‌شوند.</p></div>
    <div class="vs-field">
      <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <?php
        $allowed = (array)($s['allowed_post_types'] ?? []);
        foreach (\ViraSEO\Features\TargetKeywords::all_public_types() as $pt):
        ?>
        <label class="vs-ap-pref"><input type="checkbox" name="viraseo_settings[allowed_post_types][]" value="<?php echo esc_attr($pt['slug']); ?>" <?php checked(in_array($pt['slug'], $allowed)); ?>> <?php echo esc_html($pt['label']); ?></label>
        <?php endforeach; ?>
      </div>
      <span class="vs-hint">اگر هیچ‌کدام تیک نخورد = همه نوع‌ها فعال.</span>
    </div>

    <h3 class="vs-card-title">🤖 هوش مصنوعی (OpenRouter)</h3>
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span><p>با فعال‌سازی هوش مصنوعی، افزونه تحلیل‌های فوق‌پیشرفته ارائه می‌دهد: استراتژی شکست رقبا، طرح نگارش، و کمک به ساخت/بازنویسی محتوا بر اساس Helpful Content گوگل. کلید را از <a href="https://openrouter.ai/keys" target="_blank">openrouter.ai/keys</a> بگیرید.</p></div>
    <div class="vs-field"><label class="vs-label"><input type="checkbox" name="viraseo_settings[ai_enabled]" value="1" <?php checked(!empty($s['ai_enabled'])); ?>> فعال‌سازی هوش مصنوعی</label></div>
    <div class="vs-field"><label class="vs-label">کلید OpenRouter API</label><input class="vs-input vs-input-ltr" name="viraseo_settings[openrouter_key]" value="<?php echo esc_attr($s['openrouter_key'] ?? ''); ?>" placeholder="sk-or-..."></div>
    <div class="vs-field"><label class="vs-label">آدرس پروکسی OpenRouter (Cloudflare Worker)</label><input class="vs-input vs-input-ltr" name="viraseo_settings[ai_proxy_url]" value="<?php echo esc_attr($s['ai_proxy_url'] ?? ''); ?>" placeholder="https://viraseo-ai.your-account.workers.dev"><span class="vs-hint">اگر هاست شما در ایران به OpenRouter دسترسی ندارد، Worker موجود در پوشه <code>openrouter-proxy/</code> را در Cloudflare دیپلوی و آدرسش را اینجا وارد کنید.</span></div>
    <div class="vs-field"><label class="vs-label">پروکسی سفارشی (SOCKS/HTTP — مثل Xray)</label><input class="vs-input vs-input-ltr" name="viraseo_settings[ai_curl_proxy]" value="<?php echo esc_attr($s['ai_curl_proxy'] ?? ''); ?>" placeholder="socks5h://127.0.0.1:1080 یا http://user:pass@ip:port"><span class="vs-hint">اگر روی سرورتان Xray/پروکسی دارید، درخواست‌های AI از این پروکسی عبور می‌کنند. (نیازمند پشتیبانی cURL از پروکسی روی هاست). برای مدل‌های کند صبر کنید؛ timeout تا ۱۲۰ ثانیه است.</span></div>
    <div class="vs-field"><button type="button" class="vs-btn vs-btn-secondary vs-btn-sm" id="vs-test-proxy">🔌 تست پروکسی</button> <span id="vs-proxy-result" class="vs-hint"></span></div>
    <div class="vs-field"><label class="vs-label">مدل هوش مصنوعی</label>
      <select class="vs-input vs-input-ltr" id="vs-ai-model" name="viraseo_settings[ai_model]"><option value="<?php echo esc_attr($s['ai_model'] ?? ''); ?>"><?php echo esc_html($s['ai_model'] ?? 'انتخاب مدل'); ?></option></select>
      <button type="button" class="vs-btn vs-btn-secondary vs-btn-sm" id="vs-ai-load-models">بارگذاری مدل‌ها + هزینه</button>
      <span class="vs-hint" id="vs-ai-model-cost"></span>
    </div>
    <div class="vs-field"><label class="vs-label">حداکثر طول پاسخ AI (توکن)</label><div class="vs-row" style="gap:10px;align-items:center"><input type="number" class="vs-input vs-input-ltr" name="viraseo_settings[ai_max_tokens]" value="<?php echo esc_attr($s['ai_max_tokens'] ?? '4000'); ?>" min="1000" max="16000" style="max-width:120px"><select class="vs-input" name="viraseo_settings[ai_token_mode]" style="max-width:200px"><option value="auto" <?php selected(($s['ai_token_mode'] ?? 'auto'), 'auto'); ?>>خودکار (پیشنهادی)</option><option value="manual" <?php selected(($s['ai_token_mode'] ?? 'auto'), 'manual'); ?>>دستی</option></select></div><span class="vs-hint">حالت «خودکار»: افزونه بر اساس نوع عملیات تعداد مناسب توکن انتخاب میکند. حالت «دستی»: مقدار وارد‌شده همیشه استفاده میشود. مقدار بالاتر = محتوای بلندتر ولی زمان بیشتر.</span></div>
    </div>
    <div class="vs-toolbar"><button type="button" class="vs-btn vs-btn-secondary vs-btn-sm" id="vs-test-n8n">تست اتصال</button><span id="vs-n8n-status"></span></div>
    <h3 class="vs-card-title">تنظیمات تحلیل</h3>
    <div class="vs-row">
      <div class="vs-field"><label class="vs-label">حداقل Striking</label><input class="vs-input" type="number" name="viraseo_settings[striking_min]" value="<?php echo esc_attr($s['striking_min'] ?? 4); ?>"></div>
      <div class="vs-field"><label class="vs-label">حداکثر Striking</label><input class="vs-input" type="number" name="viraseo_settings[striking_max]" value="<?php echo esc_attr($s['striking_max'] ?? 20); ?>"></div>
      <div class="vs-field"><label class="vs-label">حداقل نمایش</label><input class="vs-input" type="number" name="viraseo_settings[min_impressions]" value="<?php echo esc_attr($s['min_impressions'] ?? 100); ?>"></div>
      <div class="vs-field"><label class="vs-label">تعداد صفحات بررسی رتبه</label><input class="vs-input" type="number" min="1" max="10" name="viraseo_settings[rank_max_pages]" value="<?php echo esc_attr($s['rank_max_pages'] ?? 3); ?>"><span class="vs-hint">هر صفحه ۱۰ نتیجه و ۱ کردیت Serper. بررسی به‌محض پیدا شدن سایت متوقف می‌شود.</span></div>
    </div>
    <h3 class="vs-card-title">هشدار افت رتبه</h3>
    <div class="vs-row">
      <div class="vs-field"><label class="vs-label"><input type="checkbox" name="viraseo_settings[rank_auto_enabled]" value="1" <?php checked(!empty($s['rank_auto_enabled'])); ?>> فعال‌سازی بررسی خودکار رتبه (پس‌زمینه)</label><span class="vs-hint">اگر خاموش باشد، هیچ بررسی خودکاری انجام نمی‌شود و <strong>کردیت Serper مصرف نمی‌شود</strong>. بررسی دستی همچنان کار می‌کند.</span></div>
      <div class="vs-field"><label class="vs-label"><input type="checkbox" name="viraseo_settings[rank_alert_email]" value="1" <?php checked(!empty($s['rank_alert_email'])); ?>> ارسال ایمیل هنگام افت رتبه</label><span class="vs-hint">ایمیل به مدیر سایت (<?php echo esc_html(get_option('admin_email')); ?>)</span></div>
      <div class="vs-field"><label class="vs-label">آستانه افت (تعداد رتبه)</label><input class="vs-input" type="number" min="1" max="50" name="viraseo_settings[rank_alert_threshold]" value="<?php echo esc_attr($s['rank_alert_threshold'] ?? 3); ?>"><span class="vs-hint">اگر رتبه این مقدار یا بیشتر افت کند، هشدار ثبت می‌شود.</span></div>
    </div>
    <h3 class="vs-card-title">منطقه خطر</h3>
    <div class="vs-field"><label class="vs-label"><input type="checkbox" name="viraseo_settings[remove_data]" value="1"> حذف تمام داده‌ها هنگام حذف پلاگین</label></div>
    <?php submit_button('ذخیره تنظیمات', 'vs-btn vs-btn-success'); ?>
  </form>
</div>
