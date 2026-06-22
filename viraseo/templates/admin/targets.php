<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title"><span class="dashicons dashicons-filter"></span> مدیریت کلمات هدف</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
  </div>

  <div class="vs-alert vs-alert-info">
    <span class="dashicons dashicons-info"></span>
    <p>برای هر صفحه کلمه‌ی هدف را مشخص کنید. افزونه بر اساس داده‌های سرچ کنسول کلمه‌ای <strong>پیشنهاد می‌دهد</strong> (دکمه «استفاده») و شما هم می‌توانید <strong>دستی</strong> وارد کنید. سپس با دکمه «تحلیل SERP» همان کلمه را مستقیماً در بخش تحلیل رقبا بررسی کنید. کلمه‌ی هدف در لینک‌سازی هوشمند و خوشه‌بندی هم استفاده می‌شود.</p>
  </div>

  <div class="vs-toolbar">
    <input type="text" class="vs-input" id="vs-tg-search" placeholder="جستجوی صفحه..." style="max-width:300px;">
    <button class="vs-btn vs-btn-secondary vs-btn-sm" id="vs-tg-reload"><span class="dashicons dashicons-update"></span> بارگذاری</button>
    <a class="vs-btn vs-btn-secondary vs-btn-sm" href="<?php echo admin_url('admin.php?page=viraseo-gsc'); ?>">🎯 تخصیص خودکار از سرچ کنسول</a>
  </div>

  <table class="vs-table">
    <thead><tr>
      <th>صفحه</th><th>کلمه هدف فعلی</th><th>منبع</th><th>عملکرد GSC</th><th>پیشنهاد افزونه</th><th>عملیات</th>
    </tr></thead>
    <tbody id="vs-tg-tbody"><tr><td colspan="6" class="vs-empty">در حال بارگذاری...</td></tr></tbody>
  </table>
</div>
