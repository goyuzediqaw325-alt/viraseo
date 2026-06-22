<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">کشف کلمات کلیدی</h1>
    <span class="vs-badge vs-badge-blue">🔵 نیازمند n8n</span>
  </div>
  <div class="vs-toolbar">
    <input type="text" class="vs-input vs-input-lg" id="vs-disc-seed" placeholder="کلمه کلیدی بذر...">
    <button class="vs-btn vs-btn-primary" id="vs-disc-start">شروع کشف</button>
    <span id="vs-disc-status"></span>
  </div>
  <div id="vs-disc-results" style="display:none">
    <div class="vs-toolbar">
      <button class="vs-btn vs-btn-secondary" id="vs-disc-brief">ساخت بریف محتوا</button>
    </div>
    <table class="vs-table">
      <thead><tr><th><input type="checkbox" id="vs-disc-all"></th><th>کلمه</th><th>حجم جستجو</th><th>سختی</th><th>نوع</th></tr></thead>
      <tbody id="vs-disc-tbody"></tbody>
    </table>
  </div>
</div>
