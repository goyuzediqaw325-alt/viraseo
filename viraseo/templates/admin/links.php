<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">لینک‌سازی داخلی</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
  </div>
  <div class="vs-toolbar">
    <button class="vs-btn vs-btn-primary" id="vs-scan-links">اسکن لینک‌ها</button>
    <span id="vs-scan-status"></span>
  </div>
  <div class="vs-tabs">
    <button class="vs-tab active" data-tab="orphans">صفحات یتیم</button>
    <button class="vs-tab" data-tab="suggestions">پیشنهادات</button>
  </div>
  <div class="vs-tab-panel active" data-panel="orphans">
    <table class="vs-table">
      <thead><tr><th>عنوان</th><th>نوع</th><th>لینک ورودی</th><th>لینک خروجی</th><th>عملیات</th></tr></thead>
      <tbody id="vs-orphans-tbody"></tbody>
    </table>
    <p class="vs-empty">صفحه یتیمی یافت نشد.</p>
  </div>
  <div class="vs-tab-panel" data-panel="suggestions">
    <div id="vs-suggestions-list"></div>
  </div>
</div>
