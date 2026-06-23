<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title"><span class="dashicons dashicons-superhero-alt"></span> سئوی ۲۰۲۶ (هوش مصنوعی + فارسی)</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
  </div>

  <div class="vs-tabs">
    <button class="vs-tab active" data-tab="ai">🤖 آمادگی هوش مصنوعی</button>
    <button class="vs-tab" data-tab="fresh">🔄 محتوای کهنه</button>
    <button class="vs-tab" data-tab="fa">📝 کیفیت متن فارسی</button>
    <button class="vs-tab" data-tab="llms">📄 llms.txt</button>
  </div>

  <div class="vs-tab-panel active" data-panel="ai">
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span>
      <p>بهینه‌سازی برای موتورهای پاسخ‌گو (AI Overviews گوگل، ChatGPT و...). صفحاتی که نیاز به بهبود دارند نمایش داده می‌شوند: پاراگراف پاسخ کوتاه، زیرعنوان‌های پرسشی، فهرست‌ها، بخش سوالات متداول و عمق محتوا.</p>
    </div>
    <button class="vs-btn vs-btn-primary" id="vs-ai-load"><span class="dashicons dashicons-update"></span> تحلیل آمادگی AI</button>
    <table class="vs-table" style="margin-top:12px;"><thead><tr><th>صفحه</th><th>امتیاز AI</th><th>پیشنهادها</th><th>عملیات</th></tr></thead><tbody id="vs-ai-tbody"><tr><td colspan="4" class="vs-empty">دکمه تحلیل را بزنید.</td></tr></tbody></table>
  </div>

  <div class="vs-tab-panel" data-panel="fresh">
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span>
      <p>صفحاتی که مدت‌هاست به‌روز نشده‌اند ولی هنوز در گوگل نمایش می‌گیرند — به‌روزرسانی آن‌ها بیشترین تأثیر را دارد (سیگنال تازگی محتوا).</p>
    </div>
    <div class="vs-toolbar"><label class="vs-hint">قدیمی‌تر از (ماه):</label><input type="number" class="vs-input" id="vs-fresh-months" value="6" min="1" max="48" style="max-width:90px;"><button class="vs-btn vs-btn-primary" id="vs-fresh-load">بررسی محتوای کهنه</button></div>
    <table class="vs-table"><thead><tr><th>صفحه</th><th>آخرین به‌روزرسانی</th><th>قدمت (ماه)</th><th>نمایش GSC</th><th>اولویت</th><th>عملیات</th></tr></thead><tbody id="vs-fresh-tbody"><tr><td colspan="6" class="vs-empty">دکمه بررسی را بزنید.</td></tr></tbody></table>
  </div>

  <div class="vs-tab-panel" data-panel="fa">
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span>
      <p>بررسی کیفیت نگارش فارسی: نیم‌فاصله (ZWNJ) در «می‌/نمی‌» و «ها»، حروف عربی (ي/ك) به‌جای فارسی (ی/ک)، و خوانایی (طول جملات).</p>
    </div>
    <button class="vs-btn vs-btn-primary" id="vs-fa-load"><span class="dashicons dashicons-update"></span> بررسی کیفیت متن فارسی</button>
    <table class="vs-table" style="margin-top:12px;"><thead><tr><th>صفحه</th><th>مشکلات نگارشی</th><th>عملیات</th></tr></thead><tbody id="vs-fa-tbody"><tr><td colspan="3" class="vs-empty">دکمه بررسی را بزنید.</td></tr></tbody></table>
  </div>

  <div class="vs-tab-panel" data-panel="llms">
    <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span>
      <p><strong>llms.txt</strong> یک استاندارد نوظهور (مثل robots.txt برای هوش مصنوعی) است که صفحات کلیدی سایت را به مدل‌های زبانی معرفی می‌کند. این فایل به‌صورت زنده در آدرس زیر سرو می‌شود:</p>
      <p><code id="vs-llms-url"></code></p>
    </div>
    <button class="vs-btn vs-btn-primary" id="vs-llms-gen"><span class="dashicons dashicons-update"></span> تولید پیش‌نمایش llms.txt</button>
    <button class="vs-btn vs-btn-secondary" id="vs-llms-copy">📋 کپی</button>
    <textarea id="vs-llms-content" class="vs-input vs-input-ltr" rows="16" style="margin-top:12px;width:100%;font-family:monospace;" readonly></textarea>
  </div>
</div>
