<?php
defined('ABSPATH') || exit;
?>
<div class="wrap viraseo-wrap" dir="rtl">
    <h1 class="viraseo-page-title">
        <span class="dashicons dashicons-cart"></span>
        سئو ووکامرس
    </h1>

    <div class="viraseo-tabs-wrapper">
        <nav class="viraseo-tabs">
            <a href="#" class="viraseo-tab active" data-tab="oos">محافظ ناموجودی (OOS)</a>
            <a href="#" class="viraseo-tab" data-tab="faceted">کنترل فیلترها</a>
        </nav>

        <div class="viraseo-tab-content active" id="tab-oos">
            <div class="viraseo-info-box">
                <span class="dashicons dashicons-lightbulb"></span>
                <p>محصولات ناموجود با ترافیک → بلوک جایگزین. بدون ترافیک + منقضی → ریدایرکت ۳۰۱.</p>
            </div>
            <table class="viraseo-table">
                <thead>
                    <tr><th>محصول</th><th>ترافیک</th><th>اقدام</th><th>تاریخ</th></tr>
                </thead>
                <tbody id="viraseo-oos-tbody">
                    <tr><td colspan="4" class="viraseo-empty-state">محصول ناموجودی شناسایی نشده.</td></tr>
                </tbody>
            </table>
        </div>


        <div class="viraseo-tab-content" id="tab-faceted">
            <div class="viraseo-info-box warning">
                <span class="dashicons dashicons-shield"></span>
                <p>اگر تعداد فیلترها بیش از حد مجاز باشد، noindex/nofollow اعمال می‌شود تا بودجه کراول هدر نرود.</p>
            </div>
            <form id="viraseo-faceted-form" class="viraseo-form">
                <div class="viraseo-form-row">
                    <label><input type="checkbox" name="enabled" id="viraseo-fac-enabled" /> فعال‌سازی محافظ</label>
                </div>
                <div class="viraseo-form-row">
                    <label>حداکثر پارامتر مجاز: <input type="number" name="max_params_allowed" value="1" min="0" max="10" class="small-text" /></label>
                </div>
                <div class="viraseo-form-row">
                    <label>پارامترهای فیلتر (هر خط یکی):<br>
                        <textarea name="filter_params_text" rows="5" class="large-text" dir="ltr" placeholder="min_price&#10;max_price&#10;pa_color"></textarea>
                    </label>
                </div>
                <div class="viraseo-form-row">
                    <label>پارامترهای امن (هر خط یکی):<br>
                        <textarea name="safe_params_text" rows="3" class="large-text" dir="ltr" placeholder="product_cat&#10;paged&#10;s"></textarea>
                    </label>
                </div>
                <div class="viraseo-form-row">
                    <label>پیشوند ویژگی‌ها: <input type="text" name="prefix" value="pa_" dir="ltr" class="small-text" /></label>
                </div>
                <div class="viraseo-form-row">
                    <label><input type="checkbox" name="noindex_sorting" /> noindex مرتب‌سازی (orderby)</label>
                </div>
                <div class="viraseo-form-row">
                    <label><input type="checkbox" name="add_canonical" /> افزودن canonical تمیز</label>
                </div>
                <button type="submit" class="button button-primary">ذخیره تنظیمات فیلتر</button>
            </form>
        </div>
    </div>
</div>
