<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">تحلیل رقبا SERP</h1>
    <span class="vs-badge vs-badge-blue">🔵 نیازمند n8n</span>
  </div>
  <div class="vs-toolbar">
    <input type="text" class="vs-input vs-input-lg" id="vs-serp-kw" placeholder="کلمه کلیدی هدف را وارد کنید...">
    <button class="vs-btn vs-btn-primary" id="vs-serp-start">شروع تحلیل</button>
  </div>
  <div id="vs-serp-progress" style="display:none">
    <div class="vs-progress"><div class="vs-progress-bar"></div><span class="vs-progress-text">در حال تحلیل...</span></div>
  </div>
  <div id="vs-serp-results" style="display:none">
    <div class="vs-stats" id="vs-serp-stats"></div>
    <table class="vs-table">
      <thead><tr><th>#</th><th>دامنه</th><th>عنوان</th><th>کلمات</th><th>هدینگ</th><th>تصاویر</th></tr></thead>
      <tbody id="vs-serp-tbody"></tbody>
    </table>
    <h3 class="vs-card-title">کلمات LSI</h3>
    <div class="vs-tags" id="vs-lsi-tags"></div>
    <h3 class="vs-card-title">شکاف محتوایی</h3>
    <ul id="vs-gap-list"></ul>
  </div>
</div>
