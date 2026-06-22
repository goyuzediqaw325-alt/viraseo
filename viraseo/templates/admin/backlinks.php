<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">بک‌لینک CRM</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
  </div>
  <div class="vs-tabs">
    <button class="vs-tab active" data-tab="backlinks">بک‌لینک‌ها</button>
    <button class="vs-tab" data-tab="disavow">Disavow</button>
  </div>
  <div class="vs-tab-panel active" data-panel="backlinks">
    <form id="vs-bl-form" class="vs-card">
      <div class="vs-row">
        <div class="vs-field"><label class="vs-label">URL مبدا</label><input class="vs-input vs-input-ltr" name="source_url"></div>
        <div class="vs-field"><label class="vs-label">URL مقصد</label><input class="vs-input vs-input-ltr" name="target_url"></div>
      </div>
      <div class="vs-row">
        <div class="vs-field"><label class="vs-label">انکرتکست</label><input class="vs-input" name="anchor"></div>
        <div class="vs-field"><label class="vs-label">نوع</label><select class="vs-select" name="type"><option value="guest">گست پست</option><option value="directory">دایرکتوری</option><option value="exchange">تبادل</option><option value="buy">خرید</option></select></div>
      </div>
      <div class="vs-row">
        <div class="vs-field"><label class="vs-label">هزینه (تومان)</label><input class="vs-input" name="cost" type="number"></div>
        <div class="vs-field"><label class="vs-label">DA</label><input class="vs-input" name="da" type="number"></div>
        <div class="vs-field"><label class="vs-label">تاریخ</label><input class="vs-input" name="date_jalali" placeholder="1403/01/01"></div>
      </div>
      <div class="vs-field"><label class="vs-label"><input type="checkbox" name="dofollow" checked> Dofollow</label></div>
      <button type="submit" class="vs-btn vs-btn-success">ذخیره بک‌لینک</button>
    </form>
    <table class="vs-table"><thead><tr><th>مبدا</th><th>انکر</th><th>نوع</th><th>DA</th><th>تاریخ</th><th>عملیات</th></tr></thead><tbody id="vs-bl-tbody"></tbody></table>
  </div>
  <div class="vs-tab-panel" data-panel="disavow">
    <div class="vs-toolbar">
      <input class="vs-input vs-input-ltr" id="vs-disavow-entry" placeholder="URL or domain">
      <select class="vs-select" id="vs-disavow-type"><option value="url">URL</option><option value="domain">Domain</option></select>
      <input class="vs-input" id="vs-disavow-reason" placeholder="دلیل...">
      <button class="vs-btn vs-btn-danger vs-btn-sm" id="vs-add-disavow">افزودن</button>
      <button class="vs-btn vs-btn-secondary vs-btn-sm" id="vs-gen-disavow">ساخت فایل</button>
    </div>
    <table class="vs-table"><thead><tr><th>آدرس</th><th>نوع</th><th>دلیل</th><th>عملیات</th></tr></thead><tbody id="vs-disavow-tbody"></tbody></table>
    <div id="vs-disavow-preview" style="display:none"><pre class="vs-code"></pre></div>
  </div>
</div>
