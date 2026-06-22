<?php defined('ABSPATH') || exit; ?>
<?php $s = \ViraSEO\Admin\Dashboard::get(); ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">تنظیمات</h1>
  </div>
  <form method="post" action="options.php" class="vs-card">
    <?php settings_fields('viraseo_opts'); ?>
    <h3 class="vs-card-title">سرچ کنسول گوگل</h3>
    <div class="vs-field"><label class="vs-label">Client ID</label><input class="vs-input vs-input-ltr" name="viraseo_settings[gsc_client_id]" value="<?php echo esc_attr($s['gsc_client_id'] ?? ''); ?>"></div>
    <div class="vs-field"><label class="vs-label">Client Secret</label><input class="vs-input vs-input-ltr" name="viraseo_settings[gsc_client_secret]" value="<?php echo esc_attr($s['gsc_client_secret'] ?? ''); ?>"></div>
    <h3 class="vs-card-title">n8n</h3>
    <div class="vs-field"><label class="vs-label">آدرس n8n</label><input class="vs-input vs-input-ltr" name="viraseo_settings[n8n_url]" value="<?php echo esc_attr($s['n8n_url'] ?? ''); ?>" placeholder="https://n8n.example.com"></div>
    <div class="vs-field"><label class="vs-label">Secret Webhook</label><input class="vs-input vs-input-ltr" name="viraseo_settings[n8n_secret]" value="<?php echo esc_attr($s['n8n_secret'] ?? ''); ?>"></div>
    <div class="vs-toolbar"><button type="button" class="vs-btn vs-btn-secondary vs-btn-sm" id="vs-test-n8n">تست اتصال</button><span id="vs-n8n-status"></span></div>
    <h3 class="vs-card-title">تنظیمات تحلیل</h3>
    <div class="vs-row">
      <div class="vs-field"><label class="vs-label">حداقل Striking</label><input class="vs-input" type="number" name="viraseo_settings[striking_min]" value="<?php echo esc_attr($s['striking_min'] ?? 4); ?>"></div>
      <div class="vs-field"><label class="vs-label">حداکثر Striking</label><input class="vs-input" type="number" name="viraseo_settings[striking_max]" value="<?php echo esc_attr($s['striking_max'] ?? 20); ?>"></div>
      <div class="vs-field"><label class="vs-label">حداقل نمایش</label><input class="vs-input" type="number" name="viraseo_settings[min_impressions]" value="<?php echo esc_attr($s['min_impressions'] ?? 100); ?>"></div>
    </div>
    <h3 class="vs-card-title">منطقه خطر</h3>
    <div class="vs-field"><label class="vs-label"><input type="checkbox" name="viraseo_settings[remove_data]" value="1"> حذف تمام داده‌ها هنگام حذف پلاگین</label></div>
    <?php submit_button('ذخیره تنظیمات', 'vs-btn vs-btn-success'); ?>
  </form>
</div>
