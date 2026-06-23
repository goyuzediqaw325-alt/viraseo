<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
$s = get_option('viraseo_settings', []);
if (empty($s['remove_data'])) return;
global $wpdb;
$tables = ['gsc_keywords','cannibalization','serp_analysis','serp_competitors','internal_links','orphan_pages','link_suggestions','backlinks','disavow','oos_log','keyword_discoveries','keyword_ideas','rank_tracking','ai_outputs','keyword_plan','activity_log'];
foreach ($tables as $t) $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}viraseo_{$t}");
delete_option('viraseo_version');
delete_option('viraseo_settings');
delete_option('viraseo_gsc_token');
