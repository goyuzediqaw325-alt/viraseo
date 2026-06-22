<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">پیش‌بینی رشد ترافیک</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
  </div>

  <div class="vs-alert vs-alert-info">
    <span class="dashicons dashicons-lightbulb"></span>
    <p>کلماتی که در صفحه ۱ پایین یا صفحه ۲-۳ گوگل هستید (جایگاه ۴ تا ۳۰). با بهبود این‌ها بیشترین رشد ترافیک را می‌گیرید. اولویت بر اساس «بیشترین رشد با کمترین تلاش» مرتب شده.</p>
  </div>

  <div class="vs-toolbar">
    <label class="vs-label" style="margin-left:8px;">اگر به این جایگاه برسم:</label>
    <select class="vs-select" id="vs-fc-target">
      <option value="1">جایگاه ۱ (CTR ۳۱٪)</option>
      <option value="3" selected>جایگاه ۳ (CTR ۱۸٪)</option>
      <option value="5">جایگاه ۵ (CTR ۹٪)</option>
      <option value="10">جایگاه ۱۰ (CTR ۲.۵٪)</option>
    </select>
    <button class="vs-btn vs-btn-primary" id="vs-fc-calc">محاسبه</button>
  </div>

  <div class="vs-stats">
    <div class="vs-stat"><div class="vs-stat-icon green"><span class="dashicons dashicons-chart-line"></span></div><div><div class="vs-stat-num" id="vs-fc-total">۰</div><div class="vs-stat-label">کلیک اضافه ماهانه</div></div></div>
    <div class="vs-stat"><div class="vs-stat-icon"><span class="dashicons dashicons-yes"></span></div><div><div class="vs-stat-num" id="vs-fc-count">۰</div><div class="vs-stat-label">فرصت یافت‌شده</div></div></div>
  </div>

  <table class="vs-table">
    <thead><tr><th>کلمه کلیدی</th><th>جایگاه فعلی</th><th>نمایش/ماه</th><th>کلیک فعلی</th><th>کلیک پتانسیل</th><th>رشد</th><th>سختی</th><th>پیشنهاد</th></tr></thead>
    <tbody id="vs-fc-tbody"><tr><td colspan="8" class="vs-empty">دکمه «محاسبه» را بزنید. (ابتدا داده‌های سرچ کنسول را همگام‌سازی کنید)</td></tr></tbody>
  </table>
</div>
