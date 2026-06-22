<?php
/**
 * WooCommerce SEO Admin Page Template
 * Contains: OOS Traffic Protector + Faceted Navigation Controller
 *
 * @package AdvancedPersianSEO
 */

defined('ABSPATH') || exit;
?>
<div class="wrap apseo-wrap" dir="rtl">
    <h1>
        <span class="dashicons dashicons-cart"></span>
        <?php _e('سئو ووکامرس', 'advanced-persian-seo'); ?>
    </h1>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper apseo-tabs">
        <a href="#oos" class="nav-tab nav-tab-active" data-tab="oos">
            <span class="dashicons dashicons-warning"></span>
            <?php _e('محافظ ناموجودی', 'advanced-persian-seo'); ?>
        </a>
        <a href="#faceted" class="nav-tab" data-tab="faceted">
            <span class="dashicons dashicons-filter"></span>
            <?php _e('کنترل فیلترها (Crawl Budget)', 'advanced-persian-seo'); ?>
        </a>
    </nav>

    <!-- Tab: OOS Traffic Protector -->
    <div class="apseo-tab-content active" id="tab-oos">
        <div class="apseo-info-box">
            <span class="dashicons dashicons-lightbulb"></span>
            <p>
                <?php _e(
                    'این سیستم محصولات ناموجود را بررسی می‌کند. اگر محصولی ترافیک ارگانیک داشته باشد، بلوک «محصولات جایگزین» نمایش داده می‌شود. اگر بدون ترافیک و منقضی باشد، ریدایرکت ۳۰۱ خودکار اعمال می‌شود.',
                    'advanced-persian-seo'
                ); ?>
            </p>
        </div>

        <!-- OOS Stats -->
        <div class="apseo-summary-cards">
            <div class="apseo-summary-card">
                <span class="dashicons dashicons-products"></span>
                <span class="apseo-card-number" id="oos-total">-</span>
                <span class="apseo-card-label"><?php _e('کل محصولات ناموجود', 'advanced-persian-seo'); ?></span>
            </div>
            <div class="apseo-summary-card apseo-card-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <span class="apseo-card-number" id="oos-with-traffic">-</span>
                <span class="apseo-card-label"><?php _e('دارای ترافیک (محافظت‌شده)', 'advanced-persian-seo'); ?></span>
            </div>
            <div class="apseo-summary-card apseo-card-warning">
                <span class="dashicons dashicons-migrate"></span>
                <span class="apseo-card-number" id="oos-redirected">-</span>
                <span class="apseo-card-label"><?php _e('ریدایرکت ۳۰۱ شده', 'advanced-persian-seo'); ?></span>
            </div>
            <div class="apseo-summary-card apseo-card-critical">
                <span class="dashicons dashicons-editor-help"></span>
                <span class="apseo-card-number" id="oos-pending">-</span>
                <span class="apseo-card-label"><?php _e('نیاز به بررسی', 'advanced-persian-seo'); ?></span>
            </div>
        </div>


        <!-- Filters -->
        <div class="apseo-filters-bar">
            <select id="apseo-oos-filter" class="apseo-select">
                <option value="all"><?php _e('همه', 'advanced-persian-seo'); ?></option>
                <option value="has_traffic"><?php _e('دارای ترافیک', 'advanced-persian-seo'); ?></option>
                <option value="no_traffic"><?php _e('بدون ترافیک', 'advanced-persian-seo'); ?></option>
                <option value="redirected"><?php _e('ریدایرکت شده', 'advanced-persian-seo'); ?></option>
            </select>
        </div>

        <!-- OOS Products Table -->
        <table class="wp-list-table widefat fixed striped apseo-table">
            <thead>
                <tr>
                    <th><?php _e('محصول', 'advanced-persian-seo'); ?></th>
                    <th class="apseo-col-narrow"><?php _e('ترافیک', 'advanced-persian-seo'); ?></th>
                    <th><?php _e('اقدام انجام‌شده', 'advanced-persian-seo'); ?></th>
                    <th><?php _e('ریدایرکت به', 'advanced-persian-seo'); ?></th>
                    <th><?php _e('شناسایی', 'advanced-persian-seo'); ?></th>
                    <th class="apseo-col-narrow"><?php _e('عملیات', 'advanced-persian-seo'); ?></th>
                </tr>
            </thead>
            <tbody id="apseo-oos-tbody">
                <tr>
                    <td colspan="6" class="apseo-loading-cell">
                        <?php _e('در حال بارگذاری...', 'advanced-persian-seo'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="apseo-pagination" id="apseo-oos-pagination"></div>
    </div>

    <!-- Tab: Faceted Navigation Controller -->
    <div class="apseo-tab-content" id="tab-faceted">
        <div class="apseo-info-box apseo-info-warning">
            <span class="dashicons dashicons-shield"></span>
            <p>
                <?php _e(
                    'فیلترهای ووکامرس (قیمت، رنگ، سایز و...) آدرس‌های بی‌نهایت تولید می‌کنند. این سیستم با تزریق noindex/nofollow از هدررفت بودجه کراول گوگل‌بات جلوگیری می‌کند.',
                    'advanced-persian-seo'
                ); ?>
            </p>
        </div>

        <!-- Faceted Nav Settings Form -->
        <div class="apseo-card">
            <h3 class="apseo-card-title">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('تنظیمات محافظ Crawl Budget', 'advanced-persian-seo'); ?>
            </h3>

            <form id="apseo-faceted-form" class="apseo-form">
                <table class="form-table apseo-form-table">
                    <tr>
                        <th scope="row">
                            <label for="faceted-enabled">
                                <?php _e('فعال‌سازی', 'advanced-persian-seo'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="faceted-enabled" name="enabled" value="1" />
                                <?php _e('محافظت از بودجه کراول فعال باشد', 'advanced-persian-seo'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="faceted-max-params">
                                <?php _e('حداکثر پارامتر مجاز', 'advanced-persian-seo'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" id="faceted-max-params" name="max_params_allowed"
                                   min="0" max="10" value="1" class="small-text" />
                            <p class="description">
                                <?php _e('اگر تعداد فیلترها در URL بیشتر از این عدد باشد، noindex اعمال می‌شود.', 'advanced-persian-seo'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="faceted-filter-params">
                                <?php _e('پارامترهای فیلتر (هر خط یکی)', 'advanced-persian-seo'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="faceted-filter-params" name="filter_params_text"
                                      rows="8" class="large-text ltr-input" dir="ltr"
                                      placeholder="min_price&#10;max_price&#10;pa_color&#10;pa_size"></textarea>
                            <p class="description">
                                <?php _e('پارامترهایی که به عنوان فیلتر شناخته می‌شوند.', 'advanced-persian-seo'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="faceted-safe-params">
                                <?php _e('پارامترهای امن (نادیده گرفته شوند)', 'advanced-persian-seo'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="faceted-safe-params" name="safe_params_text"
                                      rows="4" class="large-text ltr-input" dir="ltr"
                                      placeholder="product_cat&#10;paged&#10;s"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="faceted-prefix">
                                <?php _e('پیشوند ویژگی‌ها', 'advanced-persian-seo'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="faceted-prefix" name="custom_filter_prefix"
                                   value="pa_" class="regular-text ltr-input" dir="ltr" />
                            <p class="description">
                                <?php _e('پارامترهایی که با این پیشوند شروع شوند، فیلتر محسوب می‌شوند.', 'advanced-persian-seo'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('گزینه‌های اضافی', 'advanced-persian-seo'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="noindex_sorting" value="1" />
                                <?php _e('مرتب‌سازی (orderby) هم noindex شود', 'advanced-persian-seo'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="add_canonical" value="1" />
                                <?php _e('Canonical به آدرس تمیز (بدون فیلتر) اضافه شود', 'advanced-persian-seo'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <button type="submit" class="button button-primary">
                    <?php _e('ذخیره تنظیمات', 'advanced-persian-seo'); ?>
                </button>
            </form>
        </div>

        <!-- URL Tester -->
        <div class="apseo-card">
            <h3 class="apseo-card-title">
                <span class="dashicons dashicons-search"></span>
                <?php _e('تست آدرس URL', 'advanced-persian-seo'); ?>
            </h3>
            <p class="description">
                <?php _e('یک آدرس فیلتر شده را وارد کنید تا ببینید آیا noindex می‌شود یا خیر.', 'advanced-persian-seo'); ?>
            </p>
            <div class="apseo-form-row">
                <input type="url" id="apseo-test-url" class="large-text ltr-input" dir="ltr"
                       placeholder="https://your-site.com/shop/?min_price=100&pa_color=red&pa_size=xl" />
                <button type="button" id="apseo-test-url-btn" class="button">
                    <?php _e('تست کن', 'advanced-persian-seo'); ?>
                </button>
            </div>
            <div id="apseo-test-result" class="apseo-test-result" style="display:none;"></div>
        </div>
    </div>
</div>
