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
    <button class="vs-tab" data-tab="power">💪 قدرت و گراف لینک‌ها</button>
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
    <div class="vs-filter-chips" id="vs-sugg-filters">
      <button class="vs-chip active" data-type="">همه <span id="vs-cnt-all">0</span></button>
      <button class="vs-chip" data-type="exact">🎯 دقیق <span id="vs-cnt-exact">0</span></button>
      <button class="vs-chip" data-type="partial">🧩 جزئی <span id="vs-cnt-partial">0</span></button>
      <button class="vs-chip" data-type="semantic">🔗 معنایی <span id="vs-cnt-semantic">0</span></button>
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

  <div class="vs-tab-panel" data-panel="power">
    <div class="vs-alert vs-alert-info">
      <span class="dashicons dashicons-chart-area"></span>
      <p><strong>قدرت لینک داخلی</strong> (الگوریتم PageRank داخلی) نشان می‌دهد هر صفحه چقدر از طریق لینک‌های داخلی «قدرت» دریافت می‌کند. صفحات هدف مهم باید قدرت بالایی داشته باشند. برای به‌روزرسانی، «اسکن لینک‌ها» را بزنید.</p>
    </div>
    <div class="vs-card">
      <h3 class="vs-card-title">🕸️ گراف لینک‌های داخلی (۳۵ صفحه برتر)</h3>
      <div id="vs-link-graph" class="vs-link-graph"><span class="vs-empty">در حال رسم گراف...</span></div>
    </div>
    <div class="vs-card" style="margin-top:16px;">
      <h3 class="vs-card-title">💪 امتیاز قدرت لینک صفحات</h3>
      <table class="vs-table">
        <thead><tr><th>صفحه</th><th>لینک ورودی</th><th>قدرت لینک</th></tr></thead>
        <tbody id="vs-power-tbody"><tr><td colspan="3" class="vs-empty">ابتدا «اسکن لینک‌ها» را اجرا کنید.</td></tr></tbody>
      </table>
    </div>
  </div>
</div>
