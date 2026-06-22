<?php
defined('ABSPATH') || exit;
?>
<div class="wrap viraseo-wrap" dir="rtl">
    <h1 class="viraseo-page-title">
        <span class="dashicons dashicons-lightbulb"></span>
        کشف کلمات کلیدی فارسی
    </h1>

    <p class="viraseo-page-desc">کلمه پایه را وارد کنید. سیستم از Google Autocomplete و جستجوهای مرتبط، کلمات Long-tail و سوالات رایج استخراج می‌کند.</p>

    <div class="viraseo-card viraseo-input-card">
        <label for="viraseo-seed-kw"><strong>کلمه کلیدی پایه:</strong></label>
        <div class="viraseo-input-row">
            <input type="text" id="viraseo-seed-kw" class="viraseo-large-input" placeholder="مثال: خرید لپ‌تاپ گیمینگ" />
            <button type="button" id="viraseo-start-discover" class="button button-primary button-large">
                <span class="dashicons dashicons-search"></span> کشف کلمات
            </button>
        </div>
        <span id="viraseo-disc-status" class="viraseo-inline-status"></span>
    </div>

    <div id="viraseo-disc-results" style="display:none;">
        <div class="viraseo-toolbar">
            <button type="button" id="viraseo-gen-brief" class="button button-secondary" disabled>
                <span class="dashicons dashicons-edit"></span> تولید پیش‌نویس محتوا
            </button>
        </div>
        <table class="viraseo-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="viraseo-disc-all" /></th>
                    <th>کلمه کلیدی</th>
                    <th>منبع</th>
                    <th>ارتباط %</th>
                    <th>سوال؟</th>
                </tr>
            </thead>
            <tbody id="viraseo-disc-tbody"></tbody>
        </table>
    </div>
</div>
