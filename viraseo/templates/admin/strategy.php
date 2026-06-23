<?php
defined('ABSPATH') || exit;
$ai_on = \ViraSEO\Api\AiClient::is_enabled();
?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title"><span class="dashicons dashicons-networking"></span> استراتژی و برنامه‌ی کلمات کلیدی</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل / ⚡ AI</span>
  </div>

  <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span>
    <p>کلمات تحقیق‌شده را اینجا جمع کنید، در خوشه/سیلو دسته‌بندی کنید، اولویت بدهید و سپس برایشان محتوا (پیش‌نویس) بسازید. هوش مصنوعی می‌تواند کل استراتژی خوشه/سیلو را پیشنهاد دهد.</p>
  </div>

  <div class="vs-row" style="gap:16px;align-items:flex-start;">
    <div class="vs-card" style="flex:1;min-width:300px;">
      <h3 class="vs-card-title">🤖 ساخت استراتژی با هوش مصنوعی</h3>
      <div class="vs-field"><label class="vs-label">موضوع / کلمه دانه</label><input class="vs-input" id="vs-stg-seed" placeholder="مثلا: طراحی سایت"></div>
      <div class="vs-field"><label class="vs-label">زمینه کسب‌وکار (اختیاری)</label><input class="vs-input" id="vs-stg-biz" placeholder="مثلا: آژانس دیجیتال در تبریز"></div>
      <button class="vs-btn vs-btn-primary" id="vs-stg-ai" <?php echo $ai_on ? '' : 'disabled title="AI فعال نیست"'; ?>>ساخت خوشه/سیلو با AI و افزودن به برنامه</button>
      <span id="vs-stg-ai-status" class="vs-hint"></span>
    </div>
    <div class="vs-card" style="flex:1;min-width:300px;">
      <h3 class="vs-card-title">➕ افزودن دستی کلمات</h3>
      <div class="vs-field"><label class="vs-label">کلمات (هر خط یا با کاما)</label><textarea class="vs-input" id="vs-stg-kws" rows="3" placeholder="کلمه اول&#10;کلمه دوم"></textarea></div>
      <div class="vs-row">
        <div class="vs-field" style="flex:1"><label class="vs-label">خوشه</label><input class="vs-input" id="vs-stg-cluster" placeholder="نام خوشه"></div>
        <div class="vs-field" style="flex:1"><label class="vs-label">هدف</label><select class="vs-input" id="vs-stg-intent"><option value="">—</option><option value="informational">اطلاعاتی</option><option value="commercial">تجاری</option><option value="transactional">خرید</option></select></div>
      </div>
      <button class="vs-btn vs-btn-secondary" id="vs-stg-add">افزودن به برنامه</button>
    </div>
  </div>

  <div class="vs-card" style="margin-top:16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <h3 class="vs-card-title" style="margin:0;">📋 برنامه‌ی کلمات (Keyword Bank)</h3>
      <button class="vs-btn vs-btn-secondary vs-btn-sm" id="vs-stg-reload"><span class="dashicons dashicons-update"></span> بارگذاری</button>
    </div>
    <div id="vs-stg-list" style="margin-top:12px;"><div class="vs-empty">در حال بارگذاری...</div></div>
  </div>
</div>
