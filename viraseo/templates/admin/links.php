<?php
defined('ABSPATH') || exit;
?>
<div class="wrap viraseo-wrap" dir="rtl">
    <h1 class="viraseo-page-title">
        <span class="dashicons dashicons-admin-links"></span>
        لینک‌سازی داخلی و صفحات یتیم
    </h1>

    <div class="viraseo-toolbar">
        <button type="button" id="viraseo-scan-links" class="button button-primary">
            <span class="dashicons dashicons-search"></span>
            اسکن مجدد سایت
        </button>
        <span id="viraseo-scan-status" class="viraseo-inline-status"></span>
    </div>

    <div class="viraseo-tabs-wrapper">
        <nav class="viraseo-tabs">
            <a href="#" class="viraseo-tab active" data-tab="orphans">صفحات یتیم</a>
            <a href="#" class="viraseo-tab" data-tab="suggestions">پیشنهادات لینک</a>
        </nav>

        <div class="viraseo-tab-content active" id="tab-orphans">
            <div class="viraseo-info-box">
                <span class="dashicons dashicons-info"></span>
                <p>صفحات یتیم: هیچ لینک داخلی از محتوای سایت به آن‌ها اشاره نمی‌کند. گوگل به سختی آن‌ها را پیدا می‌کند.</p>
            </div>
            <table class="viraseo-table">
                <thead>
                    <tr><th>عنوان</th><th>نوع</th><th>لینک ورودی</th><th>لینک خروجی</th><th>عملیات</th></tr>
                </thead>
                <tbody id="viraseo-orphans-tbody">
                    <tr><td colspan="5" class="viraseo-empty-state">ابتدا اسکن را اجرا کنید.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="viraseo-tab-content" id="tab-suggestions">
            <div class="viraseo-info-box success">
                <span class="dashicons dashicons-lightbulb"></span>
                <p>پیشنهادات بر اساس شباهت محتوایی (کلمات مشترک فارسی) تولید شده‌اند.</p>
            </div>
            <div id="viraseo-suggestions-list" class="viraseo-cards-list">
                <div class="viraseo-empty-state">پیشنهادی وجود ندارد. ابتدا اسکن کنید.</div>
            </div>
        </div>
    </div>
</div>
