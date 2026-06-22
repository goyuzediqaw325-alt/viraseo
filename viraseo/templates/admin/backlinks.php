<?php
defined('ABSPATH') || exit;
?>
<div class="wrap viraseo-wrap" dir="rtl">
    <h1 class="viraseo-page-title">
        <span class="dashicons dashicons-external"></span>
        بک‌لینک CRM و Disavow
    </h1>

    <div class="viraseo-tabs-wrapper">
        <nav class="viraseo-tabs">
            <a href="#" class="viraseo-tab active" data-tab="bl-list">بک‌لینک‌ها</a>
            <a href="#" class="viraseo-tab" data-tab="bl-disavow">Disavow</a>
        </nav>

        <div class="viraseo-tab-content active" id="tab-bl-list">
            <div class="viraseo-toolbar">
                <button type="button" id="viraseo-add-bl-btn" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span> ثبت بک‌لینک جدید
                </button>
            </div>
            <table class="viraseo-table">
                <thead>
                    <tr>
                        <th>دامنه</th><th>انکر</th><th>نوع</th>
                        <th>DA</th><th>هزینه</th><th>وضعیت</th>
                        <th>تاریخ</th><th>حذف</th>
                    </tr>
                </thead>
                <tbody id="viraseo-bl-tbody">
                    <tr><td colspan="8" class="viraseo-empty-state">هنوز بک‌لینکی ثبت نشده.</td></tr>
                </tbody>
            </table>
        </div>


        <div class="viraseo-tab-content" id="tab-bl-disavow">
            <div class="viraseo-card">
                <h3>افزودن به لیست Disavow</h3>
                <div class="viraseo-input-row">
                    <input type="text" id="viraseo-disavow-input" placeholder="example.com" dir="ltr" class="viraseo-medium-input" />
                    <select id="viraseo-disavow-type" class="viraseo-select">
                        <option value="domain">دامنه کامل</option>
                        <option value="url">آدرس URL</option>
                    </select>
                    <input type="text" id="viraseo-disavow-reason" placeholder="دلیل..." class="viraseo-medium-input" />
                    <button type="button" id="viraseo-add-disavow" class="button">افزودن</button>
                </div>
            </div>
            <div class="viraseo-toolbar">
                <button type="button" id="viraseo-gen-disavow" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span> تولید فایل Disavow
                </button>
            </div>
            <table class="viraseo-table">
                <thead>
                    <tr><th>دامنه/URL</th><th>نوع</th><th>دلیل</th></tr>
                </thead>
                <tbody id="viraseo-disavow-tbody">
                    <tr><td colspan="3" class="viraseo-empty-state">لیست خالی.</td></tr>
                </tbody>
            </table>
            <pre id="viraseo-disavow-preview" class="viraseo-code-preview" style="display:none" dir="ltr"></pre>
        </div>
    </div>
</div>
