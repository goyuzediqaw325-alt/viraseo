<?php
defined('ABSPATH') || exit;
$settings = \ViraSEO\Admin\Dashboard::get_settings();
?>
<div class="wrap viraseo-wrap" dir="rtl">
    <h1 class="viraseo-page-title">
        <span class="dashicons dashicons-admin-settings"></span>
        تنظیمات ویرا سئو
    </h1>

    <form method="post" action="options.php" class="viraseo-settings-form">
        <?php settings_fields('viraseo_settings_group'); ?>

        <div class="viraseo-card">
            <h2 class="viraseo-card-title">
                <span class="dashicons dashicons-admin-links"></span>
                اتصال به n8n
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="vs-n8n-url">آدرس پایه Webhook n8n</label></th>
                    <td>
                        <input type="url" id="vs-n8n-url" name="viraseo_settings[n8n_webhook_base_url]"
                               value="<?php echo esc_attr($settings['n8n_webhook_base_url']); ?>"
                               class="regular-text" dir="ltr" placeholder="https://your-n8n.example.com" />
                        <p class="description">آدرس سرور n8n شما بدون اسلش انتهایی. مثال: https://n8n.mysite.com</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="vs-n8n-key">کلید امنیتی مشترک</label></th>
                    <td>
                        <input type="text" id="vs-n8n-key" name="viraseo_settings[n8n_secret_key]"
                               value="<?php echo esc_attr($settings['n8n_secret_key']); ?>"
                               class="regular-text" dir="ltr" autocomplete="off" />
                        <p class="description">همین کلید باید در تنظیمات n8n هم وارد شود (هدر <code>X-ViraSEO-Secret</code>)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">تست اتصال</th>
                    <td>
                        <button type="button" id="viraseo-test-n8n" class="button button-secondary">
                            <span class="dashicons dashicons-yes-alt"></span>
                            بررسی اتصال به n8n
                        </button>
                        <span id="viraseo-test-result" class="viraseo-test-status"></span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="viraseo-card">
            <h2 class="viraseo-card-title">
                <span class="dashicons dashicons-analytics"></span>
                تنظیمات تحلیل
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row">محدوده Striking Distance</th>
                    <td>
                        <label>از جایگاه
                            <input type="number" name="viraseo_settings[striking_distance_min]"
                                   value="<?php echo esc_attr($settings['striking_distance_min']); ?>"
                                   min="1" max="100" class="small-text" />
                        </label>
                        <label>تا جایگاه
                            <input type="number" name="viraseo_settings[striking_distance_max]"
                                   value="<?php echo esc_attr($settings['striking_distance_max']); ?>"
                                   min="1" max="100" class="small-text" />
                        </label>
                        <p class="description">کلمات در این محدوده به عنوان «فرصت نزدیک» علامت‌گذاری می‌شوند.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="vs-min-imp">حداقل نمایش (Impressions)</label></th>
                    <td>
                        <input type="number" id="vs-min-imp" name="viraseo_settings[min_impressions_threshold]"
                               value="<?php echo esc_attr($settings['min_impressions_threshold']); ?>"
                               min="0" class="small-text" />
                        <p class="description">کلمات با نمایش کمتر از این مقدار نادیده گرفته می‌شوند.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="viraseo-card viraseo-card-danger">
            <h2 class="viraseo-card-title">
                <span class="dashicons dashicons-warning"></span>
                منطقه خطر
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row">حذف داده‌ها</th>
                    <td>
                        <label>
                            <input type="checkbox" name="viraseo_settings[remove_data_on_uninstall]" value="1"
                                   <?php checked($settings['remove_data_on_uninstall']); ?> />
                            در صورت حذف افزونه، تمام جداول و داده‌ها پاک شوند.
                        </label>
                        <p class="description" style="color:#dc2626;">⚠️ این عمل غیرقابل بازگشت است!</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button('ذخیره تنظیمات'); ?>
    </form>
</div>
