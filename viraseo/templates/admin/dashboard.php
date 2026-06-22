<?php
defined('ABSPATH') || exit;
$settings = \ViraSEO\Admin\Dashboard::get_settings();
$has_n8n = !empty($settings['n8n_webhook_base_url']);
?>
<div class="wrap viraseo-wrap" dir="rtl">
    <h1 class="viraseo-page-title">
        <span class="dashicons dashicons-chart-area"></span>
        ویرا سئو — داشبورد
    </h1>

    <?php if (!$has_n8n): ?>
    <div class="viraseo-notice viraseo-notice-warning">
        <span class="dashicons dashicons-warning"></span>
        <p>اتصال به n8n هنوز تنظیم نشده است. برای فعال‌سازی قابلیت‌های تحلیل، به <a href="<?php echo esc_url(admin_url('admin.php?page=viraseo-settings')); ?>">صفحه تنظیمات</a> بروید.</p>
    </div>
    <?php endif; ?>

    <div class="viraseo-stats-grid">
        <div class="viraseo-stat-card">
            <div class="viraseo-stat-icon"><span class="dashicons dashicons-search"></span></div>
            <div class="viraseo-stat-content">
                <span class="viraseo-stat-number" id="vs-stat-keywords">—</span>
                <span class="viraseo-stat-label">کلمات کلیدی</span>
            </div>
        </div>
        <div class="viraseo-stat-card">
            <div class="viraseo-stat-icon warning"><span class="dashicons dashicons-star-filled"></span></div>
            <div class="viraseo-stat-content">
                <span class="viraseo-stat-number" id="vs-stat-striking">—</span>
                <span class="viraseo-stat-label">فرصت نزدیک (Striking)</span>
            </div>
        </div>
        <div class="viraseo-stat-card">
            <div class="viraseo-stat-icon danger"><span class="dashicons dashicons-randomize"></span></div>
            <div class="viraseo-stat-content">
                <span class="viraseo-stat-number" id="vs-stat-cannibal">—</span>
                <span class="viraseo-stat-label">تعارض کنیبالایزیشن</span>
            </div>
        </div>
        <div class="viraseo-stat-card">
            <div class="viraseo-stat-icon info"><span class="dashicons dashicons-admin-links"></span></div>
            <div class="viraseo-stat-content">
                <span class="viraseo-stat-number" id="vs-stat-orphans">—</span>
                <span class="viraseo-stat-label">صفحات یتیم</span>
            </div>
        </div>
        <div class="viraseo-stat-card">
            <div class="viraseo-stat-icon success"><span class="dashicons dashicons-external"></span></div>
            <div class="viraseo-stat-content">
                <span class="viraseo-stat-number" id="vs-stat-backlinks">—</span>
                <span class="viraseo-stat-label">بک‌لینک</span>
            </div>
        </div>
    </div>

    <div class="viraseo-quick-nav">
        <h2>دسترسی سریع</h2>
        <div class="viraseo-nav-grid">
            <a href="<?php echo esc_url(admin_url('admin.php?page=viraseo-gsc')); ?>" class="viraseo-nav-item">
                <span class="dashicons dashicons-search"></span>
                <strong>سرچ کنسول</strong>
                <p>تحلیل کلمات، Striking و کنیبالایزیشن</p>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=viraseo-serp')); ?>" class="viraseo-nav-item">
                <span class="dashicons dashicons-chart-bar"></span>
                <strong>تحلیل SERP</strong>
                <p>بررسی ۱۰ نتیجه برتر گوگل فارسی</p>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=viraseo-links')); ?>" class="viraseo-nav-item">
                <span class="dashicons dashicons-admin-links"></span>
                <strong>لینک‌سازی داخلی</strong>
                <p>صفحات یتیم و پیشنهاد لینک</p>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=viraseo-backlinks')); ?>" class="viraseo-nav-item">
                <span class="dashicons dashicons-external"></span>
                <strong>بک‌لینک CRM</strong>
                <p>ثبت و مدیریت بک‌لینک + Disavow</p>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=viraseo-forecast')); ?>" class="viraseo-nav-item">
                <span class="dashicons dashicons-chart-area"></span>
                <strong>پیش‌بینی ترافیک</strong>
                <p>محاسبه رشد بر اساس CTR</p>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=viraseo-discovery')); ?>" class="viraseo-nav-item">
                <span class="dashicons dashicons-lightbulb"></span>
                <strong>کشف کلمات</strong>
                <p>Google Autocomplete فارسی</p>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=viraseo-workflows')); ?>" class="viraseo-nav-item">
                <span class="dashicons dashicons-networking"></span>
                <strong>ورکفلوهای n8n</strong>
                <p>مشاهده، ویرایش و خروجی JSON</p>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=viraseo-settings')); ?>" class="viraseo-nav-item">
                <span class="dashicons dashicons-admin-settings"></span>
                <strong>تنظیمات</strong>
                <p>اتصال n8n و پیکربندی</p>
            </a>
        </div>
    </div>

    <div class="viraseo-system-info">
        <h2>وضعیت سیستم</h2>
        <table class="viraseo-info-table">
            <tr><td>نسخه افزونه:</td><td><code><?php echo esc_html(VIRASEO_VERSION); ?></code></td></tr>
            <tr><td>اتصال n8n:</td><td><?php echo $has_n8n ? '<span class="viraseo-badge success">تنظیم‌شده ✓</span>' : '<span class="viraseo-badge danger">تنظیم‌نشده ✗</span>'; ?></td></tr>
            <tr><td>Action Scheduler:</td><td><?php echo function_exists('as_schedule_recurring_action') ? '<span class="viraseo-badge success">فعال ✓</span>' : '<span class="viraseo-badge warning">نصب نشده</span>'; ?></td></tr>
            <tr><td>PHP:</td><td><code><?php echo PHP_VERSION; ?></code></td></tr>
            <tr><td>وردپرس:</td><td><code><?php echo get_bloginfo('version'); ?></code></td></tr>
        </table>
    </div>
</div>
