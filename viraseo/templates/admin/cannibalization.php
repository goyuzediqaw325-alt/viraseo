<?php defined('ABSPATH') || exit;
$ai_on = \ViraSEO\Api\AiClient::is_enabled();
?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">⚔️ هم‌نوع‌خواری کلمات کلیدی</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
    <span class="vs-badge <?php echo $ai_on ? 'vs-badge-green' : 'vs-badge-orange'; ?>"><?php echo $ai_on ? '🟢 AI فعال' : '⏸️ AI غیرفعال'; ?></span>
  </div>

  <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span>
    <p>وقتی چند صفحه‌ی سایت شما روی یک کلمه‌ی کلیدی با هم رقابت می‌کنند، سیگنال‌های رتبه بین آن‌ها تقسیم می‌شود و <strong>هر دو</strong> ضعیف‌تر دیده می‌شوند. این بخش این تعارض‌ها را از داده‌ی سرچ کنسول پیدا می‌کند، با هوش مصنوعی تحلیل می‌کند و امکان <strong>ادغام خودکار</strong> (کانونیکال، ریدایرکت ۳۰۱ یا ادغام محتوا) را می‌دهد.</p>
  </div>

  <div class="vs-card" style="margin-bottom:16px;">
    <div class="vs-toolbar">
      <button class="vs-btn vs-btn-primary" id="vs-can-detect"><span class="dashicons dashicons-search"></span> شناسایی تعارض‌ها از سرچ کنسول</button>
      <select class="vs-input" id="vs-can-status" style="max-width:180px;">
        <option value="detected">باز (حل‌نشده)</option>
        <option value="resolved">حل‌شده</option>
        <option value="ignored">نادیده‌گرفته‌شده</option>
      </select>
      <input class="vs-input" id="vs-can-filter" placeholder="فیلتر بر اساس کلمه..." style="max-width:200px;">
      <span id="vs-can-status-msg" class="vs-hint"></span>
    </div>
  </div>

  <div id="vs-can-list"><div class="vs-empty">برای شروع، دکمه «شناسایی تعارض‌ها» را بزنید.</div></div>
</div>
