<?php
defined('ABSPATH') || exit;
$n8n_url = \ViraSEO\Admin\Dashboard::get('n8n_url');
$n8n_ready = !empty($n8n_url);
?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title"><span class="dashicons dashicons-lightbulb"></span> کشف کلمات کلیدی فارسی</h1>
    <span class="vs-badge vs-badge-blue">🔵 نیازمند n8n</span>
  </div>

  <?php if (!$n8n_ready): ?>
  <div class="vs-alert vs-alert-warning">
    <span class="dashicons dashicons-warning"></span>
    <p><strong>n8n تنظیم نشده!</strong> این قابلیت نیاز به سرور n8n دارد.<br>
    ۱. آدرس n8n را در <a href="<?php echo admin_url('admin.php?page=viraseo-settings'); ?>">تنظیمات</a> وارد کنید<br>
    ۲. ورکفلو <code>02-keyword-discovery.json</code> را در n8n خود Import و Active کنید<br>
    ۳. از صفحه <a href="<?php echo admin_url('admin.php?page=viraseo-diagnostics'); ?>">تشخیص مشکلات</a> وضعیت را بررسی کنید</p>
  </div>
  <?php else: ?>

  <p class="vs-subtitle">کلمه پایه وارد کنید. n8n از Google Autocomplete و جستجوهای مرتبط، کلمات Long-tail و سؤالات رایج فارسی استخراج می‌کند.</p>

  <div class="vs-card vs-card-glow" style="margin-bottom:20px;">
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div class="vs-field" style="flex:1;min-width:300px;margin-bottom:0;">
        <label class="vs-label">کلمه کلیدی پایه (Seed):</label>
        <input type="text" class="vs-input vs-input-lg" id="vs-disc-seed" placeholder="مثال: خرید لپ‌تاپ گیمینگ">
      </div>
      <button class="vs-btn vs-btn-primary" id="vs-disc-start" style="padding:14px 28px;">
        <span class="dashicons dashicons-search"></span> شروع کشف
      </button>
    </div>
    <span id="vs-disc-status" class="vs-status" style="margin-top:8px;display:block;"></span>
  </div>

  <div id="vs-disc-error" class="vs-alert vs-alert-danger" style="display:none;">
    <span class="dashicons dashicons-dismiss"></span>
    <p id="vs-disc-error-text"></p>
  </div>

  <div id="vs-disc-results" style="display:none;">
    <div class="vs-toolbar">
      <button class="vs-btn vs-btn-success" id="vs-disc-brief" disabled>
        <span class="dashicons dashicons-edit"></span> تولید پیش‌نویس محتوا از انتخاب‌شده‌ها
      </button>
    </div>
    <table class="vs-table">
      <thead>
        <tr>
          <th><input type="checkbox" id="vs-disc-all"></th>
          <th>کلمه کلیدی</th>
          <th>منبع</th>
          <th>ارتباط</th>
          <th>نوع</th>
        </tr>
      </thead>
      <tbody id="vs-disc-tbody"></tbody>
    </table>
  </div>

  <?php endif; ?>
</div>
