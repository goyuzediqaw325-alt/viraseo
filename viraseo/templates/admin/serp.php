<?php
defined('ABSPATH') || exit;
$n8n_url = \ViraSEO\Admin\Dashboard::get('n8n_url');
$n8n_ready = !empty($n8n_url);
?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title"><span class="dashicons dashicons-chart-bar"></span> تحلیل رقبا SERP</h1>
    <span class="vs-badge vs-badge-blue">🔵 نیازمند n8n</span>
  </div>

  <?php if (!$n8n_ready): ?>
  <div class="vs-alert vs-alert-warning">
    <span class="dashicons dashicons-warning"></span>
    <p><strong>n8n تنظیم نشده!</strong> این قابلیت نیاز به سرور n8n دارد.<br>
    ۱. آدرس n8n را در <a href="<?php echo admin_url('admin.php?page=viraseo-settings'); ?>">تنظیمات</a> وارد کنید<br>
    ۲. ورکفلو <code>01-serp-analyzer.json</code> را در n8n خود Import و Active کنید<br>
    ۳. از صفحه <a href="<?php echo admin_url('admin.php?page=viraseo-diagnostics'); ?>">تشخیص مشکلات</a> وضعیت را بررسی کنید</p>
  </div>
  <?php else: ?>

  <div class="vs-card vs-card-glow" style="margin-bottom:20px;">
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div class="vs-field" style="flex:1;min-width:300px;margin-bottom:0;">
        <label class="vs-label">کلمه کلیدی فارسی هدف:</label>
        <input type="text" class="vs-input vs-input-lg" id="vs-serp-kw" placeholder="مثال: بهترین هاست وردپرس">
      </div>
      <button class="vs-btn vs-btn-primary" id="vs-serp-start" style="padding:14px 28px;">
        <span class="dashicons dashicons-search"></span> شروع تحلیل
      </button>
    </div>
    <p style="font-size:11px;color:var(--vs-text-muted);margin-top:8px;">n8n ده نتیجه برتر گوگل فارسی را اسکرپ و تحلیل می‌کند. (۱-۳ دقیقه)</p>
  </div>

  <!-- Dedicated Competitor Analysis Section -->
  <div class="vs-card vs-competitor-analysis-card" style="margin-bottom:20px;">
    <h3 class="vs-card-title"><span class="dashicons dashicons-visibility"></span> تحلیل اختصاصی رقیب</h3>
    <p style="font-size:12px;color:var(--vs-text-muted);margin-bottom:12px;">آدرس یک صفحه رقیب و کلمه کلیدی هدف را وارد کنید تا تحلیل کامل سئو آن صفحه انجام شود.</p>
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div class="vs-field" style="flex:2;min-width:300px;margin-bottom:0;">
        <label class="vs-label">آدرس صفحه رقیب:</label>
        <input type="url" class="vs-input" id="vs-comp-url" placeholder="https://example.com/page">
      </div>
      <div class="vs-field" style="flex:1;min-width:200px;margin-bottom:0;">
        <label class="vs-label">کلمه کلیدی هدف:</label>
        <input type="text" class="vs-input" id="vs-comp-keyword" placeholder="کلمه کلیدی">
      </div>
      <button class="vs-btn vs-btn-primary" id="vs-comp-analyze" style="padding:12px 24px;">
        <span class="dashicons dashicons-search"></span> تحلیل اختصاصی
      </button>
    </div>
    <div id="vs-comp-result" style="margin-top:16px;"></div>
  </div>

  <div id="vs-serp-progress" style="display:none;">
    <div class="vs-progress"><div class="vs-progress-bar"></div><span class="vs-progress-text">n8n در حال تحلیل نتایج گوگل...</span></div>
  </div>

  <div class="vs-card" id="vs-serp-history-card" style="margin-bottom:16px;">
    <h3 class="vs-card-title"><span class="dashicons dashicons-backup"></span> تحلیل‌های اخیر (ذخیره‌شده)</h3>
    <div id="vs-serp-history" class="vs-serp-history"><span class="vs-empty">در حال بارگذاری...</span></div>
  </div>

  <div id="vs-serp-error" class="vs-alert vs-alert-danger" style="display:none;">
    <span class="dashicons dashicons-dismiss"></span>
    <p id="vs-serp-error-text"></p>
  </div>

  <div id="vs-serp-results" style="display:none;">
    <div class="vs-stats" id="vs-serp-stats"></div>
    <div class="vs-card" id="vs-serp-intent" style="display:none;margin-bottom:16px;">
      <h3 class="vs-card-title"><span class="dashicons dashicons-visibility"></span> هدف کاربر (Search Intent)</h3>
      <div id="vs-intent-body"></div>
    </div>
    <div class="vs-card">
      <h3 class="vs-card-title">۱۰ نتیجه برتر گوگل</h3>
      <div class="vs-toolbar">
        <button class="vs-btn vs-btn-primary" id="vs-serp-deep"><span class="dashicons dashicons-search"></span> 🔬 آنالیز دقیق هر ۱۰ صفحه</button>
        <button class="vs-btn vs-btn-success" id="vs-serp-batch-deep"><span class="dashicons dashicons-chart-area"></span> 📊 تحلیل جامع رقبا (همه متریک‌ها)</button>
        <button class="vs-btn vs-btn-secondary" id="vs-serp-ai">🤖 استراتژی هوش مصنوعی (شکست رقبا)</button>
        <span id="vs-serp-deep-status" class="vs-hint"></span>
      </div>
      <div id="vs-serp-conclusion" style="display:none;margin-bottom:12px;"></div>
      <div id="vs-serp-ai-box" style="display:none;margin-bottom:12px;"></div>
      <table class="vs-table">
        <thead><tr><th>#</th><th>دامنه</th><th>عنوان</th><th>توضیحات (Snippet)</th><th>تعداد کلمات</th><th>H1/H2/H3</th><th>تصاویر</th></tr></thead>
        <tbody id="vs-serp-tbody"></tbody>
      </table>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
      <div class="vs-card">
        <h3 class="vs-card-title"><span class="dashicons dashicons-tag"></span> کلمات LSI مرتبط</h3>
        <div class="vs-tags" id="vs-lsi-tags"></div>
      </div>
      <div class="vs-card">
        <h3 class="vs-card-title"><span class="dashicons dashicons-portfolio"></span> شکاف محتوایی</h3>
        <ul id="vs-gap-list" style="list-style:none;padding:0;"></ul>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>
