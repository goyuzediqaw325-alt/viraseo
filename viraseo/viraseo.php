<?php
/**
 * Plugin Name: ویرا سئو - دستیار پیشرفته سئو فارسی
 * Plugin URI: https://github.com/goyuzediqaw325-alt/viraseo
 * Description: دستیار پیشرفته سئو فارسی با اتصال به n8n — تحلیل سرچ کنسول، رقبای SERP، لینک‌سازی داخلی، بک‌لینک CRM و کشف کلمات کلیدی
 * Version: 2.0.0
 * Author: ViraSEO Team
 * Author URI: https://github.com/goyuzediqaw325-alt
 * Text Domain: viraseo
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ViraSEO
 */

defined('ABSPATH') || exit;

if (defined('VIRASEO_VERSION')) {
    return;
}

define('VIRASEO_VERSION', '2.0.0');
define('VIRASEO_FILE', __FILE__);
define('VIRASEO_DIR', plugin_dir_path(__FILE__));
define('VIRASEO_URL', plugin_dir_url(__FILE__));
define('VIRASEO_BASENAME', plugin_basename(__FILE__));
define('VIRASEO_DB_VERSION', '2.0.0');

require_once VIRASEO_DIR . 'includes/Autoloader.php';

function viraseo_activate(): void {
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('افزونه ویرا سئو نیاز به PHP نسخه 8.0 یا بالاتر دارد.', 'خطای فعال‌سازی', ['back_link' => true]);
    }
    $schema = new \ViraSEO\Database\Schema();
    $schema->create_all_tables();
    update_option('viraseo_version', VIRASEO_VERSION);
    update_option('viraseo_db_version', VIRASEO_DB_VERSION);
    if (function_exists('as_schedule_recurring_action')) {
        if (!as_next_scheduled_action('viraseo_scan_orphan_pages')) {
            as_schedule_recurring_action(time(), 6 * HOUR_IN_SECONDS, 'viraseo_scan_orphan_pages');
        }
        if (!as_next_scheduled_action('viraseo_generate_link_suggestions')) {
            as_schedule_recurring_action(time(), DAY_IN_SECONDS, 'viraseo_generate_link_suggestions');
        }
    }
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'viraseo_activate');

function viraseo_deactivate(): void {
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('viraseo_scan_orphan_pages');
        as_unschedule_all_actions('viraseo_generate_link_suggestions');
    }
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'viraseo_deactivate');

function viraseo_init(): void {
    load_plugin_textdomain('viraseo', false, dirname(VIRASEO_BASENAME) . '/languages');
    new \ViraSEO\Admin\Dashboard();
    new \ViraSEO\Api\WebhookHandler();
    new \ViraSEO\Features\SearchConsole();
    new \ViraSEO\Features\SerpAnalyzer();
    new \ViraSEO\Features\InternalSilo();
    new \ViraSEO\Features\BacklinkCRM();
    new \ViraSEO\Features\OOSProtector();
    new \ViraSEO\Features\FacetedNav();
    new \ViraSEO\Features\TrafficForecaster();
    new \ViraSEO\Features\KeywordDiscovery();
    new \ViraSEO\Features\WorkflowManager();
}
add_action('plugins_loaded', 'viraseo_init');
