<?php
defined('ABSPATH') || exit;
?>
<div class="wrap viraseo-wrap" dir="rtl">
    <h1 class="viraseo-page-title">
        <span class="dashicons dashicons-search"></span>
        سرچ کنسول و تشخیص کنیبالایزیشن
    </h1>

    <div class="viraseo-toolbar">
        <button type="button" id="viraseo-sync-gsc" class="button button-primary">
            <span class="dashicons dashicons-update"></span>
            همگام‌سازی با سرچ کنسول
        </button>
        <span id="viraseo-sync-status" class="viraseo-inline-status"></span>
    </div>

    <div class="viraseo-tabs-wrapper">
        <nav class="viraseo-tabs">
            <a href="#" class="viraseo-tab active" data-tab="keywords">کلمات کلیدی</a>
            <a href="#" class="viraseo-tab" data-tab="striking">فرصت‌های نزدیک ⭐</a>
            <a href="#" class="viraseo-tab" data-tab="cannibal">کنیبالایزیشن ⚠️</a>
        </nav>

        <div class="viraseo-tab-content active" id="tab-keywords">
            <div class="viraseo-filter-bar">
                <input type="text" id="viraseo-kw-search" placeholder="جستجوی کلمه کلیدی..." class="viraseo-search-input" />
            </div>
            <table class="viraseo-table">
                <thead>
                    <tr>
                        <th>کلمه کلیدی</th>
                        <th>کلیک</th>
                        <th>نمایش</th>
                        <th>CTR</th>
                        <th>جایگاه</th>
                        <th>صفحه</th>
                    </tr>
                </thead>
                <tbody id="viraseo-kw-tbody">
                    <tr><td colspan="6" class="viraseo-empty-state">برای مشاهده داده‌ها، ابتدا با سرچ کنسول همگام‌سازی کنید.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="viraseo-tab-content" id="tab-striking">
            <div class="viraseo-info-box">
                <span class="dashicons dashicons-lightbulb"></span>
                <p>کلمات در جایگاه ۱۱ تا ۲۰ — با کمی تلاش می‌توانند به صفحه اول برسند.</p>
            </div>
            <table class="viraseo-table">
                <thead>
                    <tr><th>کلمه کلیدی</th><th>نمایش</th><th>جایگاه</th><th>صفحه</th></tr>
                </thead>
                <tbody id="viraseo-striking-tbody">
                    <tr><td colspan="4" class="viraseo-empty-state">داده‌ای یافت نشد.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="viraseo-tab-content" id="tab-cannibal">
            <div class="viraseo-info-box warning">
                <span class="dashicons dashicons-warning"></span>
                <p>کنیبالایزیشن: چند صفحه سایت شما برای یک کلمه با هم رقابت می‌کنند و قدرت سئویی کاهش می‌یابد.</p>
            </div>
            <div id="viraseo-cannibal-list" class="viraseo-cards-list">
                <div class="viraseo-empty-state">تعارضی شناسایی نشده.</div>
            </div>
        </div>
    </div>
</div>
