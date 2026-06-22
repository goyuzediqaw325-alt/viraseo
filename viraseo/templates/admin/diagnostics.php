<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title"><span class="dashicons dashicons-admin-tools"></span> تشخیص مشکلات</h1>
  </div>

  <p class="vs-subtitle">این صفحه وضعیت تمام سرویس‌ها و اتصالات را بررسی می‌کنه و دقیقاً می‌گه مشکل از کجاست.</p>

  <div class="vs-toolbar">
    <button class="vs-btn vs-btn-primary" id="vs-run-diag">
      <span class="dashicons dashicons-search"></span>
      اجرای تشخیص کامل
    </button>
  </div>

  <div id="vs-diag-results" style="display:none;">

    <!-- Database -->
    <div class="vs-card" id="vs-diag-db">
      <h3 class="vs-card-title"><span class="dashicons dashicons-database"></span> دیتابیس</h3>
      <div id="vs-diag-db-content"></div>
    </div>

    <!-- GSC -->
    <div class="vs-card" id="vs-diag-gsc">
      <h3 class="vs-card-title"><span class="dashicons dashicons-google"></span> سرچ کنسول گوگل</h3>
      <div id="vs-diag-gsc-content"></div>
    </div>

    <!-- n8n -->
    <div class="vs-card" id="vs-diag-n8n">
      <h3 class="vs-card-title"><span class="dashicons dashicons-networking"></span> سرور n8n</h3>
      <div id="vs-diag-n8n-content"></div>
    </div>

    <!-- Data -->
    <div class="vs-card" id="vs-diag-data">
      <h3 class="vs-card-title"><span class="dashicons dashicons-chart-bar"></span> داده‌ها</h3>
      <div id="vs-diag-data-content"></div>
    </div>

    <!-- Environment -->
    <div class="vs-card" id="vs-diag-env">
      <h3 class="vs-card-title"><span class="dashicons dashicons-admin-settings"></span> محیط</h3>
      <div id="vs-diag-env-content"></div>
    </div>

  </div>
</div>
