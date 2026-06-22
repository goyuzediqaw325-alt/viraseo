<?php
defined('ABSPATH') || exit;
?>
<div class="wrap viraseo-wrap" dir="rtl">
    <h1 class="viraseo-page-title">
        <span class="dashicons dashicons-chart-bar"></span>
        تحلیل رقبا در نتایج گوگل (SERP)
    </h1>

    <p class="viraseo-page-desc">کلمه کلیدی فارسی وارد کنید. سیستم ۱۰ نتیجه برتر گوگل را تحلیل و ساختار محتوایی، کلمات LSI و شکاف‌های محتوایی را استخراج می‌کند.</p>

    <div class="viraseo-card viraseo-input-card">
        <label for="viraseo-serp-keyword"><strong>کلمه کلیدی هدف:</strong></label>
        <div class="viraseo-input-row">
            <input type="text" id="viraseo-serp-keyword" class="viraseo-large-input" placeholder="مثال: بهترین هاست وردپرس" />
            <button type="button" id="viraseo-start-serp" class="button button-primary button-large">
                <span class="dashicons dashicons-search"></span>
                شروع تحلیل
            </button>
        </div>
        <div id="viraseo-serp-progress" class="viraseo-progress" style="display:none;">
            <div class="viraseo-progress-bar"></div>
            <span>در حال تحلیل نتایج گوگل...</span>
        </div>
    </div>

    <div id="viraseo-serp-results" style="display:none;">
        <div class="viraseo-stats-grid" id="viraseo-serp-summary"></div>

        <div class="viraseo-card">
            <h3>جزئیات ۱۰ نتیجه برتر</h3>
            <table class="viraseo-table">
                <thead>
                    <tr><th>#</th><th>دامنه</th><th>عنوان</th><th>کلمات</th><th>H1</th><th>H2</th><th>H3</th><th>تصاویر</th></tr>
                </thead>
                <tbody id="viraseo-serp-tbody"></tbody>
            </table>
        </div>

        <div class="viraseo-two-col">
            <div class="viraseo-card">
                <h3>کلمات LSI فارسی</h3>
                <div id="viraseo-lsi-tags" class="viraseo-tag-cloud"></div>
            </div>
            <div class="viraseo-card">
                <h3>شکاف محتوایی (Content Gap)</h3>
                <ul id="viraseo-content-gap" class="viraseo-gap-list"></ul>
            </div>
        </div>
    </div>
</div>
