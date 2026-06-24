<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap">
    <div class="vs-header">
        <div>
            <h1 class="vs-title"><span class="dashicons dashicons-editor-code"></span> اسکیمای خودکار (Schema) <span class="vs-badge vs-badge-blue">Auto</span></h1>
            <p class="vs-subtitle">تشخیص خودکار نوع اسکیما برای هر صفحه و درج JSON-LD در فرانت‌اند سایت</p>
        </div>
    </div>

    <div class="vs-tabs">
        <button class="vs-tab active" data-tab="schema-overview">نمای کلی</button>
        <button class="vs-tab" data-tab="schema-settings">تنظیمات</button>
    </div>

    <!-- Overview Tab -->
    <div class="vs-tab-panel active" id="panel-schema-overview">
        <div class="vs-toolbar">
            <select id="vs-schema-type-filter" class="vs-select" style="width:180px">
                <option value="">همه انواع نوشته</option>
                <option value="post">نوشته (post)</option>
                <option value="page">برگه (page)</option>
                <option value="product">محصول (product)</option>
            </select>
            <button class="vs-btn vs-btn-primary" id="vs-schema-load"><span class="dashicons dashicons-update"></span> بارگذاری</button>
            <span id="vs-schema-count" class="vs-status"></span>
        </div>

        <div class="vs-card">
            <table class="vs-table">
                <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>نوع نوشته</th>
                        <th>انواع اسکیما</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody id="vs-schema-tbody">
                    <tr><td colspan="5" class="vs-empty">روی «بارگذاری» کلیک کنید.</td></tr>
                </tbody>
            </table>
            <div class="vs-pager" id="vs-schema-pager"></div>
        </div>

        <!-- Preview area -->
        <div class="vs-card" id="vs-schema-preview-card" style="display:none">
            <h3 class="vs-card-title"><span class="dashicons dashicons-visibility"></span> پیش‌نمایش اسکیما</h3>
            <div class="vs-toolbar">
                <button class="vs-btn vs-btn-sm vs-btn-secondary" id="vs-schema-copy-json"><span class="dashicons dashicons-clipboard"></span> کپی JSON</button>
                <button class="vs-btn vs-btn-sm vs-btn-secondary" id="vs-schema-close-preview">بستن</button>
            </div>
            <pre class="vs-code vs-schema-json" id="vs-schema-json"></pre>
            <div class="vs-field" style="margin-top:16px">
                <label class="vs-label">اسکیمای سفارشی (JSON-LD):</label>
                <textarea class="vs-textarea vs-input-ltr" id="vs-schema-custom-json" rows="6" placeholder='{"@context":"https://schema.org","@type":"Article",...}'></textarea>
                <div class="vs-hint">اگر خالی بگذارید، اسکیمای خودکار استفاده می‌شود.</div>
            </div>
            <button class="vs-btn vs-btn-success" id="vs-schema-save-custom"><span class="dashicons dashicons-yes"></span> ذخیره اسکیمای سفارشی</button>
        </div>
    </div>

    <!-- Settings Tab -->
    <div class="vs-tab-panel" id="panel-schema-settings">
        <div class="vs-card">
            <h3 class="vs-card-title"><span class="dashicons dashicons-admin-generic"></span> تنظیمات اسکیمای خودکار</h3>

            <div class="vs-field">
                <label class="vs-label">
                    <input type="checkbox" id="vs-schema-enabled" value="1"> فعال‌سازی درج خودکار اسکیما
                </label>
                <div class="vs-hint">در صورت غیرفعال بودن، هیچ JSON-LD توسط ویرا سئو درج نخواهد شد.</div>
            </div>

            <div class="vs-field">
                <label class="vs-label">انواع نوشته‌های شامل:</label>
                <div id="vs-schema-post-types" style="display:flex;flex-wrap:wrap;gap:12px">
                    <label><input type="checkbox" class="vs-schema-pt" value="post" checked> نوشته (post)</label>
                    <label><input type="checkbox" class="vs-schema-pt" value="page" checked> برگه (page)</label>
                    <label><input type="checkbox" class="vs-schema-pt" value="product" checked> محصول (product)</label>
                </div>
                <div class="vs-hint">انواع نوشته‌ای که از لیست خارج شوند، اسکیما دریافت نمی‌کنند.</div>
            </div>

            <div class="vs-field">
                <label class="vs-label">انواع اسکیما برای تولید خودکار:</label>
                <div id="vs-schema-auto-types" style="display:flex;flex-wrap:wrap;gap:12px">
                    <label><input type="checkbox" class="vs-schema-at" value="Article" checked> Article</label>
                    <label><input type="checkbox" class="vs-schema-at" value="Product" checked> Product</label>
                    <label><input type="checkbox" class="vs-schema-at" value="FAQPage" checked> FAQPage</label>
                    <label><input type="checkbox" class="vs-schema-at" value="HowTo" checked> HowTo</label>
                    <label><input type="checkbox" class="vs-schema-at" value="BreadcrumbList" checked> BreadcrumbList</label>
                    <label><input type="checkbox" class="vs-schema-at" value="Service" checked> Service</label>
                    <label><input type="checkbox" class="vs-schema-at" value="WebPage" checked> WebPage</label>
                </div>
            </div>

            <button class="vs-btn vs-btn-primary" id="vs-schema-settings-save"><span class="dashicons dashicons-yes"></span> ذخیره تنظیمات</button>
        </div>
    </div>
</div>
