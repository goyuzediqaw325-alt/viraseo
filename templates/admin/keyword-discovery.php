<?php
/**
 * Keyword Discovery Admin Page Template
 *
 * @package AdvancedPersianSEO
 */

defined('ABSPATH') || exit;
?>
<div class="wrap apseo-wrap" dir="rtl">
    <h1>
        <span class="dashicons dashicons-search"></span>
        <?php _e('کشف کلمات کلیدی فارسی', 'advanced-persian-seo'); ?>
    </h1>

    <p class="apseo-page-description">
        <?php _e(
            'کلمه کلیدی پایه را وارد کنید. سیستم از Google Autocomplete و جستجوهای مرتبط، کلمات Long-tail فارسی و سؤالات رایج را استخراج می‌کند. سپس می‌توانید پیش‌نویس محتوا تولید کنید.',
            'advanced-persian-seo'
        ); ?>
    </p>

    <!-- Seed Keyword Input -->
    <div class="apseo-card apseo-analysis-input">
        <div class="apseo-input-row">
            <div class="apseo-input-group apseo-input-large">
                <label for="apseo-seed-keyword">
                    <?php _e('کلمه کلیدی پایه:', 'advanced-persian-seo'); ?>
                </label>
                <input type="text"
                       id="apseo-seed-keyword"
                       class="large-text"
                       placeholder="<?php esc_attr_e('مثال: خرید لپ‌تاپ گیمینگ', 'advanced-persian-seo'); ?>"
                       dir="rtl" />
            </div>
            <button type="button" id="apseo-start-discovery" class="button button-primary button-hero">
                <span class="dashicons dashicons-search"></span>
                <?php _e('کشف کلمات', 'advanced-persian-seo'); ?>
            </button>
        </div>
        <div id="apseo-discovery-progress" class="apseo-progress-bar" style="display:none;">
            <div class="apseo-progress-fill"></div>
            <span class="apseo-progress-text">
                <?php _e('در حال جمع‌آوری از Google Suggest و جستجوهای مرتبط...', 'advanced-persian-seo'); ?>
            </span>
        </div>
    </div>


    <!-- Results Section (shown after discovery completes) -->
    <div id="apseo-discovery-results" style="display:none;">

        <!-- Summary -->
        <div class="apseo-summary-cards">
            <div class="apseo-summary-card">
                <span class="dashicons dashicons-tag"></span>
                <span class="apseo-card-number" id="disc-total">-</span>
                <span class="apseo-card-label"><?php _e('کل ایده‌ها', 'advanced-persian-seo'); ?></span>
            </div>
            <div class="apseo-summary-card apseo-card-info">
                <span class="dashicons dashicons-admin-generic"></span>
                <span class="apseo-card-number" id="disc-autocomplete">-</span>
                <span class="apseo-card-label"><?php _e('از Autocomplete', 'advanced-persian-seo'); ?></span>
            </div>
            <div class="apseo-summary-card apseo-card-success">
                <span class="dashicons dashicons-networking"></span>
                <span class="apseo-card-number" id="disc-related">-</span>
                <span class="apseo-card-label"><?php _e('جستجوهای مرتبط', 'advanced-persian-seo'); ?></span>
            </div>
            <div class="apseo-summary-card apseo-card-warning">
                <span class="dashicons dashicons-editor-help"></span>
                <span class="apseo-card-number" id="disc-questions">-</span>
                <span class="apseo-card-label"><?php _e('سؤالات (FAQ)', 'advanced-persian-seo'); ?></span>
            </div>
        </div>

        <!-- Filters & Actions -->
        <div class="apseo-card">
            <div class="apseo-filters-bar">
                <select id="apseo-disc-source" class="apseo-select">
                    <option value=""><?php _e('همه منابع', 'advanced-persian-seo'); ?></option>
                    <option value="autocomplete"><?php _e('Autocomplete', 'advanced-persian-seo'); ?></option>
                    <option value="related_search"><?php _e('جستجوهای مرتبط', 'advanced-persian-seo'); ?></option>
                    <option value="people_also_ask"><?php _e('سؤالات', 'advanced-persian-seo'); ?></option>
                </select>
                <select id="apseo-disc-status" class="apseo-select">
                    <option value="active"><?php _e('فعال', 'advanced-persian-seo'); ?></option>
                    <option value="used"><?php _e('استفاده‌شده', 'advanced-persian-seo'); ?></option>
                    <option value="dismissed"><?php _e('رد شده', 'advanced-persian-seo'); ?></option>
                </select>
                <button type="button" id="apseo-generate-brief-btn" class="button button-primary" disabled>
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e('تولید پیش‌نویس از انتخاب‌شده‌ها', 'advanced-persian-seo'); ?>
                </button>
            </div>

            <!-- Post type selector for generation -->
            <div id="apseo-brief-options" style="display:none; margin-bottom: 16px;">
                <div class="apseo-form-row">
                    <div class="apseo-form-group">
                        <label><?php _e('نوع محتوا:', 'advanced-persian-seo'); ?></label>
                        <select id="apseo-brief-posttype" class="apseo-select">
                            <option value="post"><?php _e('نوشته (Post)', 'advanced-persian-seo'); ?></option>
                            <option value="page"><?php _e('برگه (Page)', 'advanced-persian-seo'); ?></option>
                            <?php if (class_exists('WooCommerce')): ?>
                            <option value="product"><?php _e('محصول (Product)', 'advanced-persian-seo'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="apseo-form-group">
                        <label><?php _e('کلمه اصلی عنوان:', 'advanced-persian-seo'); ?></label>
                        <input type="text" id="apseo-brief-primary" class="regular-text"
                               placeholder="<?php esc_attr_e('اختیاری - پیش‌فرض: اولین انتخاب', 'advanced-persian-seo'); ?>" />
                    </div>
                    <button type="button" id="apseo-confirm-brief" class="button button-primary">
                        <?php _e('✓ ایجاد پیش‌نویس', 'advanced-persian-seo'); ?>
                    </button>
                </div>
            </div>

            <!-- Keywords List -->
            <table class="wp-list-table widefat fixed striped apseo-table">
                <thead>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" id="apseo-disc-select-all" />
                        </th>
                        <th><?php _e('کلمه کلیدی', 'advanced-persian-seo'); ?></th>
                        <th class="apseo-col-narrow"><?php _e('منبع', 'advanced-persian-seo'); ?></th>
                        <th class="apseo-col-narrow"><?php _e('ارتباط', 'advanced-persian-seo'); ?></th>
                        <th class="apseo-col-narrow"><?php _e('سؤال؟', 'advanced-persian-seo'); ?></th>
                        <th class="apseo-col-narrow"><?php _e('عملیات', 'advanced-persian-seo'); ?></th>
                    </tr>
                </thead>
                <tbody id="apseo-disc-tbody">
                    <tr>
                        <td colspan="6" class="apseo-loading-cell">
                            <?php _e('در حال بارگذاری...', 'advanced-persian-seo'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="apseo-pagination" id="apseo-disc-pagination"></div>
        </div>
    </div>

    <!-- Discovery History -->
    <div class="apseo-card apseo-history-section">
        <h3>
            <span class="dashicons dashicons-backup"></span>
            <?php _e('تاریخچه کشف کلمات', 'advanced-persian-seo'); ?>
        </h3>
        <table class="wp-list-table widefat fixed striped apseo-table">
            <thead>
                <tr>
                    <th><?php _e('کلمه پایه', 'advanced-persian-seo'); ?></th>
                    <th class="apseo-col-narrow"><?php _e('وضعیت', 'advanced-persian-seo'); ?></th>
                    <th class="apseo-col-narrow"><?php _e('تعداد ایده', 'advanced-persian-seo'); ?></th>
                    <th><?php _e('تاریخ', 'advanced-persian-seo'); ?></th>
                    <th class="apseo-col-narrow"><?php _e('مشاهده', 'advanced-persian-seo'); ?></th>
                </tr>
            </thead>
            <tbody id="apseo-disc-history-tbody">
                <tr>
                    <td colspan="5" class="apseo-loading-cell">
                        <?php _e('در حال بارگذاری...', 'advanced-persian-seo'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
