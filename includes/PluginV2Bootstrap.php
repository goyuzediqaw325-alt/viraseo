<?php
/**
 * Plugin V2 Bootstrap - Initializes Features 5-9
 *
 * This file is loaded from the main plugin file after the base
 * features are initialized. It handles:
 * - V2 database schema migration
 * - New feature class instantiation
 * - New admin menu registration
 * - REST API endpoint for keyword ideas
 *
 * @package AdvancedPersianSEO
 */

namespace APSEO;

defined('ABSPATH') || exit;

class PluginV2Bootstrap {

    /**
     * V2 database version
     */
    private const DB_VERSION_V2 = '2.0.0';

    /**
     * Initialize V2 features
     */
    public static function init(): void {
        // Check and run V2 schema migration
        self::maybe_migrate_schema();

        // Register V2 admin pages
        new \APSEO\Admin\DashboardV2();

        // Initialize V2 features
        new \APSEO\Features\OOSProtector();
        new \APSEO\Features\FacetedNavController();
        new \APSEO\Features\TrafficForecaster();
        new \APSEO\Features\KeywordDiscovery();

        // Register additional REST endpoint
        add_action('rest_api_init', [self::class, 'register_v2_routes']);
    }

    /**
     * Run V2 schema migration if needed
     */
    private static function maybe_migrate_schema(): void {
        $current_version = get_option('apseo_db_version_v2', '0');

        if (version_compare($current_version, self::DB_VERSION_V2, '<')) {
            $schema = new \APSEO\Database\SchemaV2();
            $schema->create_tables();
            update_option('apseo_db_version_v2', self::DB_VERSION_V2);
        }
    }

    /**
     * Register V2 REST API routes
     */
    public static function register_v2_routes(): void {
        // Keyword ideas endpoint (receives data from n8n)
        register_rest_route('apseo/v1', '/keyword-ideas', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_keyword_ideas'],
            'permission_callback' => [self::class, 'verify_secret'],
        ]);
    }

    /**
     * Verify webhook secret (permission callback)
     */
    public static function verify_secret(\WP_REST_Request $request): bool {
        $settings = \APSEO\Admin\Dashboard::get_settings();
        $expected = $settings['n8n_secret_key'] ?? '';

        if (empty($expected)) {
            return false;
        }

        $provided = $request->get_header('X-APSEO-Secret');
        return hash_equals($expected, (string) $provided);
    }

    /**
     * Handle incoming keyword ideas from n8n
     */
    public static function handle_keyword_ideas(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;

        $payload = $request->get_json_params();
        $discovery_id = sanitize_text_field($payload['discovery_id'] ?? '');
        $ideas = $payload['ideas'] ?? [];

        if (empty($discovery_id) || empty($ideas)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'discovery_id and ideas required',
            ], 400);
        }

        $table = $wpdb->prefix . 'apseo_keyword_ideas';
        $disc_table = $wpdb->prefix . 'apseo_keyword_discoveries';

        $inserted = 0;
        foreach ($ideas as $idea) {
            $keyword = sanitize_text_field($idea['keyword'] ?? '');
            if (empty($keyword)) continue;

            $wpdb->insert($table, [
                'discovery_id'       => $discovery_id,
                'keyword'            => $keyword,
                'keyword_hash'       => md5(mb_strtolower($keyword)),
                'source'             => sanitize_text_field($idea['source'] ?? 'autocomplete'),
                'relevance_score'    => min(100, absint($idea['relevance_score'] ?? 50)),
                'search_volume_hint' => sanitize_text_field($idea['search_volume_hint'] ?? ''),
                'is_question'        => !empty($idea['is_question']) ? 1 : 0,
                'status'             => 'active',
            ]);
            $inserted++;
        }

        // Update discovery status
        $wpdb->update($disc_table, [
            'status'       => 'completed',
            'completed_at' => current_time('mysql'),
            'ideas_count'  => $inserted,
        ], ['discovery_id' => $discovery_id]);

        return new \WP_REST_Response([
            'success'  => true,
            'inserted' => $inserted,
        ], 200);
    }

    /**
     * Run on plugin activation (called from main plugin)
     */
    public static function activate(): void {
        $schema = new \APSEO\Database\SchemaV2();
        $schema->create_tables();
        update_option('apseo_db_version_v2', self::DB_VERSION_V2);
    }

    /**
     * Run on plugin uninstall (if data removal enabled)
     */
    public static function uninstall(): void {
        $schema = new \APSEO\Database\SchemaV2();
        $schema->drop_tables();
        delete_option('apseo_db_version_v2');
    }
}
