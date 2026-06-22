<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">پیش‌بینی رشد ترافیک</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
  </div>
  <div class="vs-toolbar">
    <label class="vs-label">هدف جایگاه:</label>
    <select class="vs-select" id="vs-fc-target">
      <option value="3">جایگاه ۳</option>
      <option value="5">جایگاه ۵</option>
      <option value="8">جایگاه ۸</option>
      <option value="10">جایگاه ۱۰</option>
    </select>
    <button class="vs-btn vs-btn-primary" id="vs-fc-calc">محاسبه</button>
  </div>
  <div class="vs-card">
    <h3 class="vs-card-title">رشد کل پیش‌بینی‌شده: <span id="vs-fc-total" class="vs-stat-num">0</span> کلیک</h3>
  </div>
  <table class="vs-table">
    <thead><tr><th>کلمه</th><th>جایگاه</th><th>نمایش</th><th>کلیک فعلی</th><th>کلیک پتانسیل</th><th>رشد</th></tr></thead>
    <tbody id="vs-fc-tbody"></tbody>
  </table>
  <p class="vs-empty">ابتدا داده‌های سرچ کنسول را همگام‌سازی کنید.</p>
</div>
