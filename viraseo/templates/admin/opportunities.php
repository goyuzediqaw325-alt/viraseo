<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title"><span class="dashicons dashicons-lightbulb"></span> فرصت‌های سئو</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
  </div>

  <div class="vs-tabs">
    <button class="vs-tab active" data-tab="linkopp">🔗 فرصت‌های لینک داخلی (GSC)</button>
    <button class="vs-tab" data-tab="thin">📉 محتوای ضعیف</button>
  </div>

  <div class="vs-tab-panel active" data-panel="linkopp">
    <div class="vs-alert vs-alert-info">
      <span class="dashicons dashicons-info"></span>
      <p>صفحاتی که در سرچ کنسول <strong>نمایش (Impression) خوبی می‌گیرند</strong> اما <strong>لینک داخلی کمی</strong> دارند. این‌ها بیشترین پتانسیل رشد را دارند — با دادن لینک داخلی بیشتر به آن‌ها، رتبه‌شان تقویت می‌شود.</p>
    </div>
    <div class="vs-toolbar">
      <select class="vs-input vs-type-filter" id="vs-linkopp-type" style="max-width:180px;"><option value="all">همه نوع‌ها</option></select>
      <button class="vs-btn vs-btn-primary" id="vs-load-linkopp"><span class="dashicons dashicons-update"></span> محاسبه فرصت‌ها</button>
      <span class="vs-hint">نیازمند همگام‌سازی سرچ کنسول + اجرای «اسکن لینک‌ها».</span>
    </div>
    <table class="vs-table">
      <thead><tr><th>صفحه</th><th>نوع</th><th>نمایش</th><th>کلیک</th><th>میانگین جایگاه</th><th>لینک ورودی</th><th>عملیات</th></tr></thead>
      <tbody id="vs-linkopp-tbody"><tr><td colspan="7" class="vs-empty">دکمه «محاسبه فرصت‌ها» را بزنید.</td></tr></tbody>
    </table>
  </div>

  <div class="vs-tab-panel" data-panel="thin">
    <div class="vs-alert vs-alert-info">
      <span class="dashicons dashicons-info"></span>
      <p>صفحاتی با محتوای کم (تعداد کلمات پایین). صفحاتی که <strong>نمایش می‌گیرند ولی محتوای ضعیفی دارند</strong> در اولویت بازنویسی هستند (بیشترین بازگشت سرمایه).</p>
    </div>
    <div class="vs-toolbar">
      <label class="vs-hint">حداقل کلمات قابل قبول:</label>
      <input type="number" class="vs-input" id="vs-thin-threshold" value="300" min="50" max="2000" style="max-width:110px;">
      <select class="vs-input vs-type-filter" id="vs-thin-type" style="max-width:180px;"><option value="all">همه نوع‌ها</option></select>
      <button class="vs-btn vs-btn-primary" id="vs-load-thin"><span class="dashicons dashicons-update"></span> بررسی محتوای ضعیف</button>
    </div>
    <table class="vs-table">
      <thead><tr><th>صفحه</th><th>نوع</th><th>تعداد کلمات</th><th>نمایش (GSC)</th><th>اولویت بازنویسی</th><th>عملیات</th></tr></thead>
      <tbody id="vs-thin-tbody"><tr><td colspan="6" class="vs-empty">دکمه «بررسی محتوای ضعیف» را بزنید.</td></tr></tbody>
    </table>
  </div>
</div>
