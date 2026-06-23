<?php
defined('ABSPATH') || exit;
$connected = (bool) get_option('viraseo_gsc_token');
?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title"><span class="dashicons dashicons-google"></span> وضعیت ایندکس گوگل</h1>
    <span class="vs-badge vs-badge-green">🟢 سرچ کنسول</span>
  </div>

  <?php if (!$connected): ?>
  <div class="vs-alert vs-alert-warning"><span class="dashicons dashicons-warning"></span>
    <p>برای بررسی وضعیت ایندکس باید به <a href="<?php echo admin_url('admin.php?page=viraseo-gsc'); ?>">سرچ کنسول</a> متصل باشید.</p>
  </div>
  <?php else: ?>

  <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span>
    <p>از API بازرسی URL سرچ کنسول استفاده می‌کند تا بفهمید صفحات درست ایندکس شده‌اند یا مشکل ایندکس دارند. (هر بررسی یک درخواست به API است؛ به‌صورت دسته‌ای محدود انجام می‌شود.)</p>
  </div>

  <div class="vs-card" style="margin-bottom:16px;">
    <h3 class="vs-card-title">بررسی یک آدرس</h3>
    <div class="vs-row">
      <div class="vs-field" style="flex:1"><input class="vs-input vs-input-ltr" id="vs-idx-url" placeholder="https://example.com/page/"></div>
      <button class="vs-btn vs-btn-primary" id="vs-idx-one" style="align-self:flex-end">بررسی آدرس</button>
    </div>
    <div id="vs-idx-one-box" style="margin-top:10px;"></div>
  </div>

  <div class="vs-card">
    <h3 class="vs-card-title">بررسی دسته‌ای صفحات اخیر</h3>
    <div class="vs-toolbar">
      <label class="vs-hint">تعداد:</label>
      <input type="number" class="vs-input" id="vs-idx-limit" value="15" min="1" max="25" style="max-width:90px;">
      <button class="vs-btn vs-btn-primary" id="vs-idx-batch"><span class="dashicons dashicons-update"></span> بررسی دسته‌ای</button>
      <span id="vs-idx-summary" class="vs-hint"></span>
    </div>
    <table class="vs-table">
      <thead><tr><th>صفحه</th><th>وضعیت</th><th>پوشش (Coverage)</th><th>آخرین خزش</th><th>مشکلات</th></tr></thead>
      <tbody id="vs-idx-tbody"><tr><td colspan="5" class="vs-empty">دکمه «بررسی دسته‌ای» را بزنید.</td></tr></tbody>
    </table>
  </div>

  <?php endif; ?>
</div>
