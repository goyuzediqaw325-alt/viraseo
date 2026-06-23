<?php
defined('ABSPATH') || exit;
$ai_on = \ViraSEO\Api\AiClient::is_enabled();
$opts = \ViraSEO\Features\TargetKeywords::page_options_html();
?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title"><span class="dashicons dashicons-superhero-alt"></span> ابزارهای هوش مصنوعی</h1>
    <span class="vs-badge <?php echo $ai_on ? 'vs-badge-green' : 'vs-badge-orange'; ?>"><?php echo $ai_on ? '🟢 AI فعال' : '⏸️ AI غیرفعال'; ?></span>
  </div>

  <?php if (!$ai_on): ?>
  <div class="vs-alert vs-alert-warning"><span class="dashicons dashicons-warning"></span>
    <p>هوش مصنوعی فعال نیست. در <a href="<?php echo admin_url('admin.php?page=viraseo-settings'); ?>">تنظیمات</a> آن را فعال و کلید OpenRouter را وارد کنید.</p>
  </div>
  <?php endif; ?>

  <div class="vs-tabs">
    <button class="vs-tab active" data-tab="kw">🔑 تحقیق کلمات کلیدی</button>
    <button class="vs-tab" data-tab="review">🔍 بازبینی و تحلیل محتوا</button>
    <button class="vs-tab" data-tab="faq">❓ تولید FAQ Schema</button>
    <button class="vs-tab" data-tab="saved">💾 ذخیره‌شده‌ها</button>
  </div>

  <div class="vs-tab-panel active" data-panel="kw">
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span><p>یک موضوع یا کلمه‌ی دانه وارد کنید تا هوش مصنوعی کلمات کلیدی را بر اساس هدف کاربر (اطلاعاتی/خرید)، دم‌بلند، سوالات و خوشه‌بندی موضوعی پیشنهاد دهد.</p></div>
    <div class="vs-row">
      <div class="vs-field" style="flex:1"><label class="vs-label">موضوع / کلمه دانه</label><input class="vs-input" id="vs-aikw-seed" placeholder="مثلا: طراحی سایت"></div>
      <div class="vs-field" style="flex:1"><label class="vs-label">زمینه کسب‌وکار (اختیاری)</label><input class="vs-input" id="vs-aikw-biz" placeholder="مثلا: آژانس دیجیتال مارکتینگ در تبریز"></div>
    </div>
    <button class="vs-btn vs-btn-primary" id="vs-aikw-go"><span class="dashicons dashicons-search"></span> تحقیق کلمات با AI</button>
    <div id="vs-aikw-box" style="margin-top:14px;"></div>
  </div>

  <div class="vs-tab-panel" data-panel="review">
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span><p>هوش مصنوعی محتوای صفحه را از نظر کیفیت، Helpful Content، E-E-A-T، خوانایی و شکاف‌ها بازبینی می‌کند و اقدامات بهبود می‌دهد.</p></div>
    <div class="vs-row">
      <div class="vs-field" style="flex:1"><label class="vs-label">انتخاب صفحه</label><select class="vs-input" id="vs-airev-post"><?php echo $opts; ?></select></div>
      <button class="vs-btn vs-btn-primary" id="vs-airev-go" style="align-self:flex-end"><span class="dashicons dashicons-visibility"></span> بازبینی محتوا</button>
    </div>
    <div id="vs-airev-box" style="margin-top:14px;"></div>
  </div>

  <div class="vs-tab-panel" data-panel="faq">
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span><p>تولید سوالات متداول + کد اسکیمای FAQPage آماده‌ی کپی (برای AI Overview و ریچ‌اسنیپت).</p></div>
    <div class="vs-row">
      <div class="vs-field" style="flex:1"><label class="vs-label">انتخاب صفحه (یا کلمه هدف زیر)</label><select class="vs-input" id="vs-aifaq-post"><?php echo $opts; ?></select></div>
      <div class="vs-field" style="flex:1"><label class="vs-label">یا کلمه هدف</label><input class="vs-input" id="vs-aifaq-kw" placeholder="مثلا: قیمت طراحی سایت"></div>
      <button class="vs-btn vs-btn-primary" id="vs-aifaq-go" style="align-self:flex-end">تولید FAQ</button>
    </div>
    <div id="vs-aifaq-box" style="margin-top:14px;"></div>
  </div>

  <div class="vs-tab-panel" data-panel="saved">
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span><p>همه‌ی تحلیل‌ها و خروجی‌هایی که با دکمه «💾 ذخیره» نگه داشته‌اید اینجا هستند.</p></div>
    <button class="vs-btn vs-btn-secondary vs-btn-sm" id="vs-saved-reload"><span class="dashicons dashicons-update"></span> بارگذاری</button>
    <div id="vs-saved-list" style="margin-top:14px;"></div>
  </div>
</div>
