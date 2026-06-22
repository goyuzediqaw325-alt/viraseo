<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">سئو ووکامرس</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
  </div>
  <div class="vs-tabs">
    <button class="vs-tab active" data-tab="oos">محصولات ناموجود</button>
    <button class="vs-tab" data-tab="faceted">ناوبری فیلتری</button>
  </div>
  <div class="vs-tab-panel active" data-panel="oos">
    <table class="vs-table">
      <thead><tr><th>محصول</th><th>ترافیک</th><th>اقدام</th><th>تاریخ</th></tr></thead>
      <tbody id="vs-oos-tbody"></tbody>
    </table>
    <p class="vs-empty">محصول ناموجودی با ترافیک یافت نشد.</p>
  </div>
  <div class="vs-tab-panel" data-panel="faceted">
    <form id="vs-faceted-form" class="vs-card">
      <div class="vs-field"><label class="vs-label"><input type="checkbox" id="vs-fac-enabled"> فعال‌سازی مدیریت ناوبری فیلتری</label></div>
      <div class="vs-field"><label class="vs-label">حداکثر پارامتر مجاز</label><input type="number" class="vs-input" id="vs-fac-max" value="2"></div>
      <div class="vs-field"><label class="vs-label">فیلترهای ایندکس‌شونده</label><textarea class="vs-textarea" id="vs-fac-filters" placeholder="color&#10;size"></textarea><span class="vs-hint">هر خط یک فیلتر</span></div>
      <div class="vs-field"><label class="vs-label">ترکیبات امن</label><textarea class="vs-textarea" id="vs-fac-safe" placeholder="color+size"></textarea></div>
      <div class="vs-field"><label class="vs-label">پیشوند canonical</label><input type="text" class="vs-input vs-input-ltr" id="vs-fac-prefix" placeholder="/shop/"></div>
      <div class="vs-field"><label class="vs-label"><input type="checkbox" id="vs-fac-sort"> noindex صفحات sort</label></div>
      <button type="submit" class="vs-btn vs-btn-success">ذخیره تنظیمات</button>
    </form>
  </div>
</div>
