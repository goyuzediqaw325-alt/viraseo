<?php
defined('ABSPATH') || exit;
global $wpdb;
$p = $wpdb->prefix . 'viraseo_';
$tbl = fn($t) => $wpdb->get_var("SHOW TABLES LIKE '{$p}{$t}'") === "{$p}{$t}";
$cnt = fn($sql) => (int) $wpdb->get_var($sql);
$d_kw = $tbl('gsc_keywords') ? $cnt("SELECT COUNT(*) FROM {$p}gsc_keywords") : 0;
$d_strike = $tbl('gsc_keywords') ? $cnt("SELECT COUNT(*) FROM {$p}gsc_keywords WHERE position > 10 AND position <= 20") : 0;
$d_can = $tbl('cannibalization') ? $cnt("SELECT COUNT(*) FROM {$p}cannibalization WHERE status='detected'") : 0;
$d_orph = $tbl('orphan_pages') ? $cnt("SELECT COUNT(*) FROM {$p}orphan_pages WHERE status='orphan'") : 0;
$d_bl = $tbl('backlinks') ? $cnt("SELECT COUNT(*) FROM {$p}backlinks") : 0;
$fa = fn($n) => \ViraSEO\Utils\PersianText::format_number($n);
?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">داشبورد ویرا سئو</h1>
    <p class="vs-subtitle">نمای کلی وضعیت سئو سایت شما</p>
  </div>

  <div class="vs-card vs-card-glow" id="vs-action-plan" style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <h2 class="vs-card-title" style="margin:0;">🎯 برنامه‌ی اقدام سئو (قدم بعدی شما)</h2>
      <div style="display:flex;align-items:center;gap:12px;">
        <div id="vs-health" class="vs-health"></div>
        <button class="vs-btn vs-btn-sm vs-btn-secondary" id="vs-ap-gear" title="سفارشی‌سازی">⚙️ سفارشی‌سازی</button>
      </div>
    </div>
    <p class="vs-subtitle">افزونه مهم‌ترین کارهایی که باید برای افزایش رتبه انجام دهید را بر اساس داده‌های واقعی سایت اولویت‌بندی کرده است.</p>
    <div id="vs-ap-prefs" class="vs-ap-prefs" style="display:none;">
      <div class="vs-hint" style="margin-bottom:8px;">انتخاب کنید کدام دسته‌ها در برنامه‌ی اقدام نمایش داده شوند:</div>
      <div id="vs-ap-prefs-list" class="vs-ap-prefs-list"></div>
      <button class="vs-btn vs-btn-sm vs-btn-success" id="vs-ap-save">💾 ذخیره</button>
    </div>
    <div id="vs-action-list"><div class="vs-empty">در حال محاسبه‌ی برنامه...</div></div>
  </div>
  <div class="vs-stats">
    <div class="vs-stat" id="vs-stat-keywords"><span class="vs-stat-icon green">📊</span><span class="vs-stat-num"><?php echo $fa($d_kw); ?></span><span class="vs-stat-label">کلمات کلیدی</span></div>
    <div class="vs-stat" id="vs-stat-striking"><span class="vs-stat-icon orange">🎯</span><span class="vs-stat-num"><?php echo $fa($d_strike); ?></span><span class="vs-stat-label">Striking Distance</span></div>
    <div class="vs-stat" id="vs-stat-cannibalization"><span class="vs-stat-icon red">⚠️</span><span class="vs-stat-num"><?php echo $fa($d_can); ?></span><span class="vs-stat-label">کانیبالیزاسیون</span></div>
    <div class="vs-stat" id="vs-stat-orphans"><span class="vs-stat-icon cyan">🔗</span><span class="vs-stat-num"><?php echo $fa($d_orph); ?></span><span class="vs-stat-label">صفحات یتیم</span></div>
    <div class="vs-stat" id="vs-stat-backlinks"><span class="vs-stat-icon green">🌐</span><span class="vs-stat-num"><?php echo $fa($d_bl); ?></span><span class="vs-stat-label">بک‌لینک‌ها</span></div>
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
