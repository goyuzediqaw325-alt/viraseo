<?php
/**
 * Plugin Name: ویرا سئو — دستیار هوشمند سئو فارسی
 * Plugin URI: https://github.com/goyuzediqaw325-alt/viraseo
 * Description: ابزار پیشرفته سئو فارسی: اتصال مستقیم به سرچ کنسول، تحلیل SERP، لینک‌سازی داخلی، بک‌لینک CRM، پیش‌بینی ترافیک و کشف کلمات کلیدی
 * Version: 3.0.0
 * Author: ViraSEO
 * Author URI: https://github.com/goyuzediqaw325-alt
 * Text Domain: viraseo
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL v2 or later
 */

defined('ABSPATH') || exit;
if (defined('VIRASEO_VERSION')) return;

define('VIRASEO_VERSION', '3.0.0');
define('VIRASEO_FILE', __FILE__);
define('VIRASEO_DIR', plugin_dir_path(__FILE__));
define('VIRASEO_URL', plugin_dir_url(__FILE__));
define('VIRASEO_BASENAME', plugin_basename(__FILE__));

require_once VIRASEO_DIR . 'includes/Autoloader.php';

register_activation_hook(__FILE__, function() {
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('ویرا سئو نیاز به PHP 8.0+ دارد.', 'خطا', ['back_link' => true]);
    }
    (new \ViraSEO\Database\Schema())->create_all_tables();
    update_option('viraseo_version', VIRASEO_VERSION);
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

add_action('plugins_loaded', function() {
    load_plugin_textdomain('viraseo', false, dirname(VIRASEO_BASENAME) . '/languages');
    new \ViraSEO\Admin\Dashboard();
    new \ViraSEO\Api\WebhookHandler();
    new \ViraSEO\Api\GoogleOAuth();
    new \ViraSEO\Features\SearchConsole();
    new \ViraSEO\Features\SerpAnalyzer();
    new \ViraSEO\Features\InternalSilo();
    new \ViraSEO\Features\BacklinkCRM();
    new \ViraSEO\Features\OOSProtector();
    new \ViraSEO\Features\FacetedNav();
    new \ViraSEO\Features\TrafficForecaster();
    new \ViraSEO\Features\KeywordDiscovery();
    new \ViraSEO\Features\WorkflowManager();
    new \ViraSEO\Features\Diagnostics();
});
