<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">⚡ سرعت و Core Web Vitals</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
  </div>

  <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span>
    <p>این بخش با استفاده از <strong>Google PageSpeed Insights</strong> سه شاخص حیاتی گوگل را اندازه می‌گیرد: <strong>LCP</strong> (سرعت بارگذاری)، <strong>INP</strong> (پاسخ‌گویی به تعامل)، <strong>CLS</strong> (پایداری چیدمان). اگر داده‌ی واقعی کاربران (CrUX) موجود باشد ترجیح داده می‌شود؛ وگرنه داده‌ی آزمایشگاهی نمایش داده می‌شود. پیشنهادهای بهبود مستقیماً به فارسی ارائه می‌شوند.</p>
  </div>

  <div class="vs-card" style="margin-bottom:16px;">
    <h3 class="vs-card-title">بررسی یک آدرس</h3>
    <div class="vs-row">
      <div class="vs-field" style="flex:1"><input class="vs-input vs-input-ltr" id="vs-cwv-url" placeholder="https://example.com/page/"></div>
      <div class="vs-field"><label class="vs-label">دستگاه</label>
        <select class="vs-input" id="vs-cwv-strategy">
          <option value="mobile">موبایل (پیش‌فرض گوگل)</option>
          <option value="desktop">دسکتاپ</option>
        </select>
      </div>
      <button class="vs-btn vs-btn-primary" id="vs-cwv-one" style="align-self:flex-end">بررسی سرعت</button>
    </div>
    <div id="vs-cwv-one-box" style="margin-top:12px;"></div>
  </div>

  <div class="vs-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <h3 class="vs-card-title" style="margin:0;">بررسی دسته‌ای صفحات</h3>
      <div class="vs-toolbar" style="margin:0;">
        <label class="vs-hint">دستگاه:</label>
        <select class="vs-input" id="vs-cwv-batch-strategy" style="max-width:140px;">
          <option value="mobile">موبایل</option>
          <option value="desktop">دسکتاپ</option>
        </select>
        <label class="vs-hint">تعداد:</label>
        <input type="number" class="vs-input" id="vs-cwv-limit" value="5" min="1" max="15" style="max-width:80px;">
        <button class="vs-btn vs-btn-primary" id="vs-cwv-batch"><span class="dashicons dashicons-update"></span> بررسی دسته‌ای</button>
        <button class="vs-btn vs-btn-secondary" id="vs-cwv-load">📂 نمایش نتایج ذخیره‌شده</button>
      </div>
    </div>
    <p class="vs-hint">هر بررسی یک درخواست به PageSpeed است و کمی زمان می‌برد. برای رفع محدودیت نرخ، کلید رایگان PSI را در تنظیمات وارد کنید.</p>
    <div class="vs-toolbar">
      <input class="vs-input" id="vs-cwv-filter" placeholder="فیلتر بر اساس آدرس/عنوان..." style="max-width:240px;">
      <select class="vs-input" id="vs-cwv-vfilter" style="max-width:160px;">
        <option value="">همه وضعیت‌ها</option>
        <option value="poor">فقط ضعیف</option>
        <option value="ni">فقط نیازمند بهبود</option>
        <option value="good">فقط خوب</option>
      </select>
      <span id="vs-cwv-summary" class="vs-hint"></span>
    </div>
    <table class="vs-table">
      <thead><tr><th>صفحه</th><th>امتیاز</th><th>LCP</th><th>INP</th><th>CLS</th><th>TTFB</th><th>وضعیت</th><th></th></tr></thead>
      <tbody id="vs-cwv-tbody"><tr><td colspan="8" class="vs-empty">«بررسی دسته‌ای» را بزنید یا نتایج ذخیره‌شده را نمایش دهید.</td></tr></tbody>
    </table>
    <div class="vs-cpager vs-pager" id="vs-cwv-pager"></div>
  </div>
</div>
