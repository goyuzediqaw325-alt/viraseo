<?php
defined('ABSPATH') || exit;
$has_key = !empty(\ViraSEO\Admin\Dashboard::get('serper_api_key'));
?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title"><span class="dashicons dashicons-chart-line"></span> مانیتورینگ رتبه کلمات کلیدی</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل (Serper)</span>
  </div>

  <?php if (!$has_key): ?>
  <div class="vs-alert vs-alert-warning">
    <span class="dashicons dashicons-warning"></span>
    <p><strong>کلید Serper API تنظیم نشده!</strong> برای رصد رتبه‌ها باید کلید رایگان Serper را در
    <a href="<?php echo admin_url('admin.php?page=viraseo-settings'); ?>">تنظیمات</a> وارد کنید.</p>
  </div>
  <?php else: ?>

  <div class="vs-card vs-card-glow" style="margin-bottom:20px;">
    <h3 class="vs-card-title">افزودن کلمه برای رصد</h3>
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div class="vs-field" style="flex:2;min-width:240px;margin-bottom:0;">
        <label class="vs-label">کلمه کلیدی:</label>
        <input type="text" class="vs-input" id="vs-rank-kw" placeholder="مثال: طراحی سایت در تبریز">
      </div>
      <div class="vs-field" style="flex:1;min-width:140px;margin-bottom:0;">
        <label class="vs-label">دفعات بررسی:</label>
        <select class="vs-input" id="vs-rank-freq">
          <option value="daily">روزانه</option>
          <option value="2days">هر ۲ روز</option>
          <option value="weekly">هفتگی</option>
        </select>
      </div>
      <div class="vs-field" style="min-width:120px;margin-bottom:0;">
        <label class="vs-label">صفحات بررسی:</label>
        <input type="number" class="vs-input" id="vs-rank-pages" min="1" max="10" value="3" title="هر صفحه ۱۰ نتیجه و ۱ کردیت Serper">
      </div>
      <button class="vs-btn vs-btn-primary" id="vs-rank-add"><span class="dashicons dashicons-plus-alt"></span> افزودن</button>
      <button class="vs-btn vs-btn-secondary" id="vs-rank-checkall"><span class="dashicons dashicons-update"></span> بررسی همه الان</button>
    </div>
    <p style="font-size:11px;color:var(--vs-text-muted);margin-top:8px;">«صفحات بررسی» برای <strong>هر کلمه جداگانه</strong> تعیین می‌شود (مثلاً کلمه‌ای ۲ صفحه، کلمه‌ای ۶ صفحه). هر صفحه ۱۰ نتیجه و ۱ کردیت Serper است؛ بررسی به‌محض پیدا شدن سایت یا تمام شدن نتایج گوگل متوقف می‌شود. لوکیشن همیشه «ایران».</p>
  </div>

  <div class="vs-card">
    <h3 class="vs-card-title">کلمات تحت رصد</h3>
    <div id="vs-rank-msg" style="display:none;margin-bottom:12px;"></div>
    <table class="vs-table">
      <thead><tr>
        <th>کلمه کلیدی</th><th>رتبه فعلی</th><th>تغییر</th><th>بهترین رتبه</th>
        <th>روند (۱۴ بررسی اخیر)</th><th>URL رتبه‌گرفته</th><th>صفحات</th><th>دفعات</th><th>آخرین بررسی</th><th>عملیات</th>
      </tr></thead>
      <tbody id="vs-rank-tbody"><tr><td colspan="10" class="vs-empty">در حال بارگذاری...</td></tr></tbody>
    </table>
  </div>

  <?php endif; ?>
</div>
