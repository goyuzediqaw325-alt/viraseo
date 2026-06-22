<?php
defined('ABSPATH') || exit;
$token = get_option('viraseo_gsc_token');
$is_connected = !empty($token['access_token']);
$connected_at = $token['connected_at'] ?? '';

// Check for callback messages
$gsc_error = sanitize_text_field($_GET['gsc_error'] ?? '');
$gsc_connected = !empty($_GET['gsc_connected']);
?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title"><span class="dashicons dashicons-search"></span> سرچ کنسول گوگل</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل — بدون نیاز به n8n</span>
  </div>

  <?php if ($gsc_error): ?>
  <div class="vs-alert vs-alert-danger">
    <span class="dashicons dashicons-warning"></span>
    <p>خطا در اتصال: <?php echo esc_html($gsc_error); ?></p>
  </div>
  <?php endif; ?>

  <?php if ($gsc_connected): ?>
  <div class="vs-alert vs-alert-success">
    <span class="dashicons dashicons-yes-alt"></span>
    <p>اتصال به سرچ کنسول گوگل با موفقیت برقرار شد! ✓</p>
  </div>
  <?php endif; ?>

  <!-- Connection Box -->
  <div class="vs-card vs-card-glow" style="margin-bottom:24px;">
    <div style="display:flex;align-items:center;gap:16px;">
      <?php if ($is_connected): ?>
        <div class="vs-stat-icon green" style="width:56px;height:56px;border-radius:14px;">
          <span class="dashicons dashicons-yes-alt" style="font-size:28px;width:28px;height:28px;"></span>
        </div>
        <div style="flex:1;">
          <strong style="color:#fff;font-size:15px;display:block;">✅ اتصال برقرار</strong>
          <span style="font-size:12px;color:var(--vs-text-muted);">متصل از: <?php echo esc_html($connected_at); ?></span>
        </div>
        <button class="vs-btn vs-btn-danger vs-btn-sm" id="vs-gsc-disconnect">قطع اتصال</button>
      <?php else: ?>
        <div class="vs-stat-icon orange" style="width:56px;height:56px;border-radius:14px;">
          <span class="dashicons dashicons-admin-plugins" style="font-size:28px;width:28px;height:28px;"></span>
        </div>
        <div style="flex:1;">
          <strong style="color:#fff;font-size:15px;display:block;">به سرچ کنسول متصل نیستید</strong>
          <span style="font-size:12px;color:var(--vs-text-muted);">
            با کلیک روی دکمه زیر، به صفحه اجازه گوگل منتقل می‌شوید.<br>
            پس از تأیید، خودکار به اینجا برمی‌گردید.
          </span>
        </div>
        <button class="vs-btn vs-btn-primary" id="vs-gsc-connect" style="font-size:15px;padding:14px 28px;">
          <span class="dashicons dashicons-google"></span>
          اتصال به سرچ کنسول گوگل
        </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($is_connected): ?>
  <!-- Sync Toolbar -->
  <div class="vs-card" style="margin-bottom:20px;">
    <h3 class="vs-card-title"><span class="dashicons dashicons-admin-site-alt3"></span> انتخاب سایت و همگام‌سازی</h3>
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div class="vs-field" style="flex:1;min-width:250px;margin-bottom:0;">
        <label class="vs-label">سایت (Property) در سرچ کنسول:</label>
        <select id="vs-gsc-site" class="vs-select vs-input-ltr" style="width:100%;">
          <option value="">در حال بارگذاری لیست سایت‌ها...</option>
        </select>
      </div>
      <div class="vs-field" style="min-width:140px;margin-bottom:0;">
        <label class="vs-label">بازه زمانی:</label>
        <select id="vs-gsc-days" class="vs-select">
          <option value="7">۷ روز اخیر</option>
          <option value="28" selected>۲۸ روز اخیر</option>
          <option value="90">۹۰ روز اخیر</option>
        </select>
      </div>
      <button class="vs-btn vs-btn-primary" id="vs-gsc-sync">
        <span class="dashicons dashicons-update"></span>
        دریافت داده‌ها
      </button>
      <span id="vs-sync-status" class="vs-status"></span>
    </div>
  </div>

  <!-- Overview totals -->
  <div id="vs-gsc-overview" class="vs-stats-row" style="margin-bottom:20px;display:none;">
    <div class="vs-stat"><div class="vs-stat-icon"><span class="dashicons dashicons-admin-links"></span></div><div><span class="vs-stat-num" id="vs-gsc-t-clicks">۰</span><span class="vs-stat-label">کل کلیک‌ها</span></div></div>
    <div class="vs-stat"><div class="vs-stat-icon green"><span class="dashicons dashicons-visibility"></span></div><div><span class="vs-stat-num" id="vs-gsc-t-impr">۰</span><span class="vs-stat-label">کل نمایش‌ها</span></div></div>
    <div class="vs-stat"><div class="vs-stat-icon cyan"><span class="dashicons dashicons-chart-bar"></span></div><div><span class="vs-stat-num" id="vs-gsc-t-pos">۰</span><span class="vs-stat-label">میانگین جایگاه</span></div></div>
    <div class="vs-stat"><div class="vs-stat-icon orange"><span class="dashicons dashicons-tag"></span></div><div><span class="vs-stat-num" id="vs-gsc-t-count">۰</span><span class="vs-stat-label">تعداد کلمات</span></div></div>
  </div>

  <!-- Tabs -->
  <div class="vs-tabs">
    <button class="vs-tab active" data-tab="keywords">📊 کلمات کلیدی</button>
    <button class="vs-tab" data-tab="timeline">📅 نمای زمانی</button>
    <button class="vs-tab" data-tab="striking">⭐ فرصت نزدیک (Striking)</button>
    <button class="vs-tab" data-tab="cannibal">⚠️ کنیبالایزیشن</button>
  </div>

  <div class="vs-tab-panel active" id="panel-keywords">
    <div class="vs-toolbar">
      <input type="text" class="vs-input" id="vs-kw-search" placeholder="جستجوی کلمه کلیدی..." style="max-width:300px;">
      <button class="vs-btn vs-btn-secondary vs-btn-sm" id="vs-assign-targets" title="برای صفحاتی که کلمه هدف ندارند، پرکلیک‌ترین کوئری سرچ کنسول را به‌عنوان کلمه هدف تنظیم می‌کند">🎯 تخصیص خودکار کلمه هدف به صفحات</button>
      <span id="vs-assign-status" class="vs-hint"></span>
    </div>
    <table class="vs-table vs-table-sortable">
      <thead><tr>
        <th>کلمه کلیدی</th>
        <th class="vs-sort" data-sort="clicks">کلیک <span class="vs-sort-ar"></span></th>
        <th class="vs-sort" data-sort="impressions">نمایش <span class="vs-sort-ar">▼</span></th>
        <th class="vs-sort" data-sort="ctr">CTR <span class="vs-sort-ar"></span></th>
        <th class="vs-sort" data-sort="position">جایگاه <span class="vs-sort-ar"></span></th>
        <th>صفحه</th>
      </tr></thead>
      <tbody id="vs-kw-tbody">
        <tr><td colspan="6" class="vs-empty">دکمه «دریافت داده‌ها» را بزنید.</td></tr>
      </tbody>
    </table>
  </div>

  <div class="vs-tab-panel" id="panel-timeline">
    <div class="vs-alert vs-alert-info">
      <span class="dashicons dashicons-calendar-alt"></span>
      <p>روند کلیک و نمایش به تفکیک روز — مرتب‌شده از جدیدترین، دقیقاً مثل نمای زمانی سرچ کنسول.</p>
    </div>
    <table class="vs-table">
      <thead><tr><th>تاریخ</th><th>کلیک</th><th>نمایش</th><th>CTR</th><th>میانگین جایگاه</th></tr></thead>
      <tbody id="vs-gsc-daily-tbody">
        <tr><td colspan="5" class="vs-empty">ابتدا داده‌ها را همگام‌سازی کنید.</td></tr>
      </tbody>
    </table>
  </div>

  <div class="vs-tab-panel" id="panel-striking">
    <div class="vs-alert vs-alert-info">
      <span class="dashicons dashicons-lightbulb"></span>
      <p>کلمات در جایگاه ۱۱-۲۰ — با کمی بهینه‌سازی می‌توانند به صفحه اول گوگل بیایند.</p>
    </div>
    <table class="vs-table">
      <thead><tr><th>کلمه</th><th>نمایش</th><th>کلیک</th><th>جایگاه</th><th>صفحه</th></tr></thead>
      <tbody id="vs-striking-tbody">
        <tr><td colspan="5" class="vs-empty">ابتدا داده‌ها را همگام‌سازی کنید.</td></tr>
      </tbody>
    </table>
  </div>

  <div class="vs-tab-panel" id="panel-cannibal">
    <div class="vs-alert vs-alert-warning">
      <span class="dashicons dashicons-warning"></span>
      <p>وقتی چند صفحه سایت شما برای یک کلمه رقابت می‌کنند، قدرت همه کم می‌شه.</p>
    </div>
    <div class="vs-toolbar">
      <button class="vs-btn vs-btn-secondary" id="vs-detect-cannibal">
        <span class="dashicons dashicons-search"></span>
        تشخیص خودکار تعارض‌ها
      </button>
    </div>
    <div id="vs-cannibal-list">
      <div class="vs-empty">تعارضی شناسایی نشده. ابتدا داده‌ها را همگام‌سازی و سپس «تشخیص» بزنید.</div>
    </div>
  </div>

  <?php else: ?>
  <div class="vs-alert vs-alert-info">
    <span class="dashicons dashicons-info"></span>
    <p>برای مشاهده داده‌های کلمات کلیدی، ابتدا به سرچ کنسول متصل شوید.</p>
  </div>
  <?php endif; ?>
</div>
