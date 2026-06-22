<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">لینک‌سازی داخلی هوشمند</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
  </div>
  <div class="vs-toolbar">
    <button class="vs-btn vs-btn-primary" id="vs-scan-links"><span class="dashicons dashicons-search"></span> اسکن لینک‌ها</button>
    <span id="vs-scan-status"></span>
  </div>
  <div class="vs-tabs">
    <button class="vs-tab active" data-tab="orphans">صفحات یتیم</button>
    <button class="vs-tab" data-tab="suggestions">پیشنهادات لینک</button>
    <button class="vs-tab" data-tab="clusters">🧩 خوشه‌های موضوعی</button>
  </div>

  <div class="vs-tab-panel active" data-panel="orphans">
    <table class="vs-table">
      <thead><tr><th>عنوان</th><th>نوع</th><th>لینک ورودی</th><th>لینک خروجی</th><th>عملیات</th></tr></thead>
      <tbody id="vs-orphans-tbody"></tbody>
    </table>
  </div>

  <div class="vs-tab-panel" data-panel="suggestions">
    <div class="vs-alert vs-alert-info">
      <span class="dashicons dashicons-info"></span>
      <p>پیشنهادها بر اساس کلمات مشترک دو صفحه ساخته می‌شوند. با «درج خودکار» لینک با انکر هوشمند مستقیماً داخل محتوای صفحه مبدا قرار می‌گیرد (اولین رخداد انکر، بیرون از لینک‌های موجود).</p>
    </div>
    <div class="vs-toolbar">
      <button class="vs-btn vs-btn-success" id="vs-apply-all-links"><span class="dashicons dashicons-superhero"></span> درج خودکار همه پیشنهادها</button>
      <span id="vs-apply-all-status"></span>
    </div>
    <div id="vs-suggestions-list"></div>
  </div>

  <div class="vs-tab-panel" data-panel="clusters">
    <div class="vs-alert vs-alert-info">
      <span class="dashicons dashicons-networking"></span>
      <p>صفحات سایت بر اساس موضوع اصلی‌شان خوشه‌بندی شده‌اند. در هر خوشه، صفحه‌ی «ستون» (Pillar) پیشنهادی مشخص است — بهتر است سایر صفحات خوشه به آن لینک دهند (ساختار Silo).</p>
    </div>
    <button class="vs-btn vs-btn-secondary vs-btn-sm" id="vs-load-clusters"><span class="dashicons dashicons-update"></span> محاسبه خوشه‌ها</button>
    <div id="vs-clusters-list" style="margin-top:16px;"></div>
  </div>
</div>
