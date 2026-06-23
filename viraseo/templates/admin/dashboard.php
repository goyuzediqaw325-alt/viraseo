<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">داشبورد ویرا سئو</h1>
    <p class="vs-subtitle">نمای کلی وضعیت سئو سایت شما</p>
  </div>

  <div class="vs-card vs-card-glow" id="vs-action-plan" style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <h2 class="vs-card-title" style="margin:0;">🎯 برنامه‌ی اقدام سئو (قدم بعدی شما)</h2>
      <div id="vs-health" class="vs-health"></div>
    </div>
    <p class="vs-subtitle">افزونه مهم‌ترین کارهایی که باید برای افزایش رتبه انجام دهید را بر اساس داده‌های واقعی سایت اولویت‌بندی کرده است.</p>
    <div id="vs-action-list"><div class="vs-empty">در حال محاسبه‌ی برنامه...</div></div>
  </div>
  <div class="vs-stats">
    <div class="vs-stat" id="vs-stat-keywords"><span class="vs-stat-icon green">📊</span><span class="vs-stat-num">0</span><span class="vs-stat-label">کلمات کلیدی</span></div>
    <div class="vs-stat" id="vs-stat-striking"><span class="vs-stat-icon orange">🎯</span><span class="vs-stat-num">0</span><span class="vs-stat-label">Striking Distance</span></div>
    <div class="vs-stat" id="vs-stat-cannibalization"><span class="vs-stat-icon red">⚠️</span><span class="vs-stat-num">0</span><span class="vs-stat-label">کانیبالیزاسیون</span></div>
    <div class="vs-stat" id="vs-stat-orphans"><span class="vs-stat-icon cyan">🔗</span><span class="vs-stat-num">0</span><span class="vs-stat-label">صفحات یتیم</span></div>
    <div class="vs-stat" id="vs-stat-backlinks"><span class="vs-stat-icon green">🌐</span><span class="vs-stat-num">0</span><span class="vs-stat-label">بک‌لینک‌ها</span></div>
  </div>
  <h2 class="vs-card-title">بخش‌های پلاگین</h2>
  <div class="vs-nav-grid">
    <a class="vs-nav-item" href="?page=viraseo-gsc">سرچ کنسول<span class="vs-badge vs-badge-green">🟢 مستقل</span></a>
    <a class="vs-nav-item" href="?page=viraseo-serp">تحلیل رقبا<span class="vs-badge vs-badge-blue">🔵 نیازمند n8n</span></a>
    <a class="vs-nav-item" href="?page=viraseo-links">لینک‌سازی داخلی<span class="vs-badge vs-badge-green">🟢 مستقل</span></a>
    <a class="vs-nav-item" href="?page=viraseo-backlinks">بک‌لینک CRM<span class="vs-badge vs-badge-green">🟢 مستقل</span></a>
    <a class="vs-nav-item" href="?page=viraseo-forecast">پیش‌بینی ترافیک<span class="vs-badge vs-badge-green">🟢 مستقل</span></a>
    <a class="vs-nav-item" href="?page=viraseo-discovery">کشف کلمات<span class="vs-badge vs-badge-blue">🔵 نیازمند n8n</span></a>
    <a class="vs-nav-item" href="?page=viraseo-woo">سئو ووکامرس<span class="vs-badge vs-badge-green">🟢 مستقل</span></a>
    <a class="vs-nav-item" href="?page=viraseo-workflows">ورکفلوها<span class="vs-badge vs-badge-blue">🔵 نیازمند n8n</span></a>
  </div>
  <div class="vs-card">
    <h3 class="vs-card-title">اطلاعات سیستم</h3>
    <p>نسخه وردپرس: <?php echo get_bloginfo('version'); ?> | نسخه PHP: <?php echo PHP_VERSION; ?> | ویرا سئو: <?php echo VIRASEO_VERSION; ?></p>
  </div>
</div>
