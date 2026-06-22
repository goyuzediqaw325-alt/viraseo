<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">سرچ کنسول</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل — اتصال مستقیم به گوگل</span>
  </div>
  <div class="vs-gsc-box" id="vs-gsc-box">
    <?php if (!empty($connected)) : ?>
      <span class="vs-gsc-icon green">✅</span>
      <span class="vs-gsc-info">اتصال برقرار است</span>
      <button class="vs-btn vs-btn-danger vs-btn-sm" id="vs-gsc-disconnect">قطع اتصال</button>
    <?php else : ?>
      <span class="vs-gsc-icon">🔌</span>
      <span class="vs-gsc-info">به سرچ کنسول متصل نیستید</span>
      <button class="vs-btn vs-btn-primary" id="vs-gsc-connect">اتصال به گوگل</button>
    <?php endif; ?>
  </div>
  <div class="vs-toolbar">
    <button class="vs-btn vs-btn-secondary" id="vs-gsc-sync">همگام‌سازی</button>
    <span id="vs-sync-status"></span>
  </div>
  <div class="vs-tabs">
    <button class="vs-tab active" data-tab="keywords">کلمات کلیدی</button>
    <button class="vs-tab" data-tab="striking">Striking Distance</button>
    <button class="vs-tab" data-tab="cannibal">کانیبالیزاسیون</button>
  </div>
  <div class="vs-tab-panel active" data-panel="keywords">
    <input type="text" class="vs-input" id="vs-kw-search" placeholder="جستجوی کلمه...">
    <table class="vs-table"><thead><tr><th>کلمه</th><th>کلیک</th><th>نمایش</th><th>CTR</th><th>جایگاه</th><th>لینک</th></tr></thead><tbody id="vs-kw-tbody"></tbody></table>
    <p class="vs-empty">داده‌ای یافت نشد. ابتدا همگام‌سازی کنید.</p>
  </div>
  <div class="vs-tab-panel" data-panel="striking">
    <table class="vs-table"><thead><tr><th>کلمه</th><th>نمایش</th><th>کلیک</th><th>جایگاه</th><th>لینک</th></tr></thead><tbody id="vs-striking-tbody"></tbody></table>
  </div>
  <div class="vs-tab-panel" data-panel="cannibal">
    <button class="vs-btn vs-btn-primary" id="vs-detect-cannibal">تشخیص کانیبالیزاسیون</button>
    <div id="vs-cannibal-list"></div>
  </div>
</div>
