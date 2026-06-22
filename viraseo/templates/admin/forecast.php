<?php
defined('ABSPATH') || exit;
?>
<div class="wrap viraseo-wrap" dir="rtl">
    <h1 class="viraseo-page-title">
        <span class="dashicons dashicons-chart-area"></span>
        پیش‌بینی رشد ترافیک ارگانیک
    </h1>

    <p class="viraseo-page-desc">بر اساس مدل CTR استاندارد، پتانسیل رشد ترافیک محاسبه می‌شود.</p>

    <div class="viraseo-card viraseo-input-card">
        <div class="viraseo-input-row">
            <label><strong>جایگاه هدف:</strong>
                <select id="viraseo-fc-target" class="viraseo-select">
                    <option value="3">جایگاه ۳ (CTR: 18.6%)</option>
                    <option value="5" selected>جایگاه ۵ (CTR: 9.5%)</option>
                    <option value="8">جایگاه ۸ (CTR: 3.3%)</option>
                    <option value="10">جایگاه ۱۰ (CTR: 2.5%)</option>
                </select>
            </label>
            <button type="button" id="viraseo-fc-calc" class="button button-primary">
                <span class="dashicons dashicons-calculator"></span> محاسبه
            </button>
        </div>
    </div>

    <table class="viraseo-table">
        <thead>
            <tr>
                <th>کلمه کلیدی</th>
                <th>جایگاه فعلی</th>
                <th>نمایش/ماه</th>
                <th>کلیک فعلی</th>
                <th>کلیک پتانسیل</th>
                <th style="color:#059669;">🚀 رشد ترافیک</th>
            </tr>
        </thead>
        <tbody id="viraseo-fc-tbody">
            <tr><td colspan="6" class="viraseo-empty-state">دکمه «محاسبه» را بزنید.</td></tr>
        </tbody>
    </table>
</div>
