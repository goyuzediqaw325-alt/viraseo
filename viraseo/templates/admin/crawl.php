<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">🕷️ سلامت خزش و هاست</h1>
    <span class="vs-badge vs-badge-green">🟢 مستقل</span>
  </div>

  <div class="vs-alert vs-alert-info"><span class="dashicons dashicons-info"></span>
    <p>این بخش مشکلاتی که باعث می‌شوند گوگل سایت شما را <strong>کند یا ناقص بخزد</strong> پیدا می‌کند: تنظیمات دیده‌شدن وردپرس، robots.txt، نقشه‌ی سایت، زمان پاسخ سرور (TTFB)، فشرده‌سازی، کش، و هدرهای ناخواسته‌ی noindex. سپس راهکار فارسی برای هر مورد می‌دهد.</p>
  </div>

  <div class="vs-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <button class="vs-btn vs-btn-primary" id="vs-crawl-run"><span class="dashicons dashicons-update"></span> اجرای بررسی خزش</button>
      <div id="vs-crawl-score" class="vs-health"></div>
    </div>
    <p class="vs-hint">بررسی شامل چند درخواست به صفحه‌ی اصلی، robots.txt، نقشه‌ی سایت و چند صفحه‌ی نمونه است و ممکن است چند ثانیه طول بکشد.</p>
    <div id="vs-crawl-list" style="margin-top:12px;"><div class="vs-empty">برای شروع، «اجرای بررسی خزش» را بزنید.</div></div>
  </div>
</div>
