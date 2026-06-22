<?php
/**
 * Traffic ROI Forecaster Admin Page Template
 *
 * @package AdvancedPersianSEO
 */

defined('ABSPATH') || exit;
?>
<div class="wrap apseo-wrap" dir="rtl">
    <h1>
        <span class="dashicons dashicons-chart-area"></span>
        <?php _e('پیش‌بینی رشد ترافیک ارگانیک', 'advanced-persian-seo'); ?>
    </h1>

    <p class="apseo-page-description">
        <?php _e(
            'بر اساس داده‌های سرچ کنسول و مدل CTR استاندارد، پتانسیل رشد ترافیک هر کلمه کلیدی محاسبه می‌شود. کلمات با بالاترین بازدهی (ROI) برای لینک‌سازی و بهینه‌سازی اولویت‌بندی شده‌اند.',
            'advanced-persian-seo'
        ); ?>
    </p>

    <!-- Scenario Summary Cards -->
    <div class="apseo-card">
        <h3 class="apseo-card-title">
            <span class="dashicons dashicons-chart-line"></span>
            <?php _e('خلاصه پیش‌بینی ترافیک ماهانه', 'advanced-persian-seo'); ?>
        </h3>

        <div class="apseo-summary-cards">
            <div class="apseo-summary-card">
                <span class="dashicons dashicons-editor-textcolor"></span>
                <span class="apseo-card-number" id="fc-total-kw">-</span>
                <span class="apseo-card-label"><?php _e('کلمه در محدوده Striking', 'advanced-persian-seo'); ?></span>
            </div>
            <div class="apseo-summary-card apseo-card-success">
                <span class="dashicons dashicons-arrow-up-alt"></span>
                <span class="apseo-card-number" id="fc-optimistic">-</span>
                <span class="apseo-card-label"><?php _e('خوش‌بینانه (جایگاه ۳)', 'advanced-persian-seo'); ?></span>
            </div>
            <div class="apseo-summary-card apseo-card-info">
                <span class="dashicons dashicons-arrow-up-alt"></span>
                <span class="apseo-card-number" id="fc-moderate">-</span>
                <span class="apseo-card-label"><?php _e('متعادل (جایگاه ۵)', 'advanced-persian-seo'); ?></span>
            </div>
            <div class="apseo-summary-card apseo-card-warning">
                <span class="dashicons dashicons-arrow-up-alt"></span>
                <span class="apseo-card-number" id="fc-conservative">-</span>
                <span class="apseo-card-label"><?php _e('محتاطانه (جایگاه ۸)', 'advanced-persian-seo'); ?></span>
            </div>
        </div>

        <p class="apseo-card-hint" style="text-align:center; margin-top:12px;">
            <?php _e('اعداد بالا = تعداد کلیک ماهانه اضافی در صورت رسیدن به جایگاه هدف', 'advanced-persian-seo'); ?>
        </p>
    </div>

    <!-- CTR Curve Chart -->
    <div class="apseo-card apseo-chart-container">
        <h3><?php _e('منحنی CTR ارگانیک (درصد کلیک بر اساس جایگاه)', 'advanced-persian-seo'); ?></h3>
        <canvas id="apseo-ctr-curve-chart" height="120"></canvas>
    </div>


    <!-- Forecast Controls -->
    <div class="apseo-card">
        <h3 class="apseo-card-title">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('تنظیمات پیش‌بینی', 'advanced-persian-seo'); ?>
        </h3>
        <div class="apseo-form-row">
            <div class="apseo-form-group">
                <label><?php _e('جایگاه هدف:', 'advanced-persian-seo'); ?></label>
                <select id="apseo-fc-target" class="apseo-select">
                    <option value="1"><?php _e('جایگاه ۱ (۳۱.۷% CTR)', 'advanced-persian-seo'); ?></option>
                    <option value="3"><?php _e('جایگاه ۳ (۱۸.۶% CTR)', 'advanced-persian-seo'); ?></option>
                    <option value="5" selected><?php _e('جایگاه ۵ (۹.۵% CTR)', 'advanced-persian-seo'); ?></option>
                    <option value="8"><?php _e('جایگاه ۸ (۳.۳% CTR)', 'advanced-persian-seo'); ?></option>
                    <option value="10"><?php _e('جایگاه ۱۰ (۲.۵% CTR)', 'advanced-persian-seo'); ?></option>
                </select>
            </div>
            <div class="apseo-form-group">
                <label><?php _e('محدوده جایگاه فعلی:', 'advanced-persian-seo'); ?></label>
                <input type="number" id="apseo-fc-min-pos" value="11" min="2" max="100" class="small-text" />
                <span> — </span>
                <input type="number" id="apseo-fc-max-pos" value="30" min="2" max="100" class="small-text" />
            </div>
            <div class="apseo-form-group">
                <label><?php _e('حداقل نمایش ماهانه:', 'advanced-persian-seo'); ?></label>
                <input type="number" id="apseo-fc-min-imp" value="50" min="1" class="small-text" />
            </div>
            <div class="apseo-form-group">
                <label><?php _e('مرتب‌سازی:', 'advanced-persian-seo'); ?></label>
                <select id="apseo-fc-sort" class="apseo-select">
                    <option value="traffic_growth"><?php _e('بیشترین رشد ترافیک', 'advanced-persian-seo'); ?></option>
                    <option value="roi_score"><?php _e('بالاترین ROI', 'advanced-persian-seo'); ?></option>
                    <option value="impressions"><?php _e('بیشترین نمایش', 'advanced-persian-seo'); ?></option>
                    <option value="effort_score"><?php _e('آسان‌ترین (کمترین تلاش)', 'advanced-persian-seo'); ?></option>
                </select>
            </div>
            <button type="button" id="apseo-fc-calculate" class="button button-primary">
                <span class="dashicons dashicons-calculator"></span>
                <?php _e('محاسبه', 'advanced-persian-seo'); ?>
            </button>
        </div>
    </div>

    <!-- Forecast Results Table -->
    <div class="apseo-card">
        <h3 class="apseo-card-title">
            <span class="dashicons dashicons-list-view"></span>
            <?php _e('فرصت‌های رشد ترافیک', 'advanced-persian-seo'); ?>
        </h3>

        <table class="wp-list-table widefat fixed striped apseo-table">
            <thead>
                <tr>
                    <th><?php _e('کلمه کلیدی', 'advanced-persian-seo'); ?></th>
                    <th class="apseo-col-narrow"><?php _e('جایگاه فعلی', 'advanced-persian-seo'); ?></th>
                    <th class="apseo-col-narrow"><?php _e('نمایش/ماه', 'advanced-persian-seo'); ?></th>
                    <th class="apseo-col-narrow"><?php _e('کلیک فعلی', 'advanced-persian-seo'); ?></th>
                    <th class="apseo-col-narrow"><?php _e('کلیک پتانسیل', 'advanced-persian-seo'); ?></th>
                    <th class="apseo-col-narrow" style="color:green;">
                        <?php _e('🚀 رشد ترافیک', 'advanced-persian-seo'); ?>
                    </th>
                    <th class="apseo-col-narrow"><?php _e('تلاش', 'advanced-persian-seo'); ?></th>
                    <th><?php _e('اولویت', 'advanced-persian-seo'); ?></th>
                </tr>
            </thead>
            <tbody id="apseo-forecast-tbody">
                <tr>
                    <td colspan="8" class="apseo-loading-cell">
                        <?php _e('برای مشاهده نتایج، دکمه «محاسبه» را بزنید.', 'advanced-persian-seo'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="apseo-pagination" id="apseo-fc-pagination"></div>
    </div>

    <!-- Formula Explanation -->
    <div class="apseo-card" style="background: var(--apseo-gray-50);">
        <h3 class="apseo-card-title">
            <span class="dashicons dashicons-info-outline"></span>
            <?php _e('نحوه محاسبه', 'advanced-persian-seo'); ?>
        </h3>
        <div style="font-family: monospace; direction: ltr; text-align: left; padding: 12px; background: #fff; border-radius: 4px; border: 1px solid var(--apseo-gray-200);">
            Potential Clicks = Monthly Impressions × Target Position CTR<br>
            Traffic Growth = Potential Clicks − Current Clicks<br>
            Effort Score = f(position_gap, impressions, current_clicks)<br>
            ROI Score = Traffic Growth × (100 / Effort Score)
        </div>
        <p style="margin-top: 12px; color: var(--apseo-gray-500); font-size: 12px;">
            <?php _e(
                'مدل CTR بر اساس میانگین صنعتی (Advanced Web Ranking) تنظیم شده. CTR واقعی بسته به نوع کوئری و فیچرهای SERP متفاوت است.',
                'advanced-persian-seo'
            ); ?>
        </p>
    </div>
</div>
