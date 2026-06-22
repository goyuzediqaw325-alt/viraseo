<?php
/**
 * Database Schema V2 - Additional tables for Features 5, 7, 8, 9
 *
 * These tables extend the base plugin schema (Schema.php).
 * Called during plugin activation if db_version < 2.0.0.
 *
 * New tables:
 * - apseo_oos_log: Tracks out-of-stock products and actions taken
 * - apseo_keyword_discoveries: Discovery request tracking
 * - apseo_keyword_ideas: Individual keyword ideas from n8n
 *
 * Modified tables (ALTER):
 * - apseo_serp_analysis: +keyword_intent, +ecommerce_data
 * - apseo_serp_competitors: +ecommerce_signals
 *
 * @package AdvancedPersianSEO\Database
 */

namespace APSEO\Database;

defined('ABSPATH') || exit;

class SchemaV2 {

    /**
     * Run all V2 schema updates
     */
    public function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $this->create_oos_log_table($wpdb, $charset_collate);
        $this->create_keyword_discoveries_table($wpdb, $charset_collate);
        $this->create_keyword_ideas_table($wpdb, $charset_collate);
        $this->alter_serp_tables($wpdb);
    }


    /**
     * TABLE: OOS (Out-of-Stock) Traffic Protection Log
     *
     * Tracks WooCommerce products that went out of stock,
     * whether they have organic traffic, and what action was taken.
     *
     * Design Notes:
     * - One row per product (UNIQUE on product_id)
     * - has_traffic: determined from apseo_gsc_keywords data
     * - action_taken: show_alternatives | redirected_301 | pending_review
     * - Row deleted when product comes back in stock
     */
    private function create_oos_log_table(\wpdb $wpdb, string $charset): void {
        $table = $wpdb->prefix . 'apseo_oos_log';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            product_title VARCHAR(500) DEFAULT NULL,
            stock_status VARCHAR(20) DEFAULT 'outofstock',
            has_traffic TINYINT(1) DEFAULT 0,
            action_taken ENUM('show_alternatives','redirected_301','pending_review') DEFAULT 'pending_review',
            redirect_url VARCHAR(2048) DEFAULT NULL,
            monthly_impressions INT(10) UNSIGNED DEFAULT 0,
            monthly_clicks INT(10) UNSIGNED DEFAULT 0,
            detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_checked DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_product (product_id),
            KEY idx_has_traffic (has_traffic),
            KEY idx_action (action_taken),
            KEY idx_detected (detected_at)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * TABLE: Keyword Discovery Requests
     *
     * Tracks each keyword discovery session initiated by the user.
     * Links to apseo_keyword_ideas via discovery_id.
     *
     * Design Notes:
     * - discovery_id: MD5 hash used as foreign key (not auto-increment)
     * - status: processing | completed | failed
     * - ideas_count: total ideas received from n8n
     */
    private function create_keyword_discoveries_table(\wpdb $wpdb, string $charset): void {
        $table = $wpdb->prefix . 'apseo_keyword_discoveries';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            discovery_id VARCHAR(64) NOT NULL,
            seed_keyword VARCHAR(500) NOT NULL,
            status ENUM('processing','completed','failed') DEFAULT 'processing',
            ideas_count INT(10) UNSIGNED DEFAULT 0,
            requested_by BIGINT(20) UNSIGNED NOT NULL,
            requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_discovery (discovery_id),
            KEY idx_seed (seed_keyword(191)),
            KEY idx_status (status),
            KEY idx_requested_at (requested_at)
        ) {$charset};";

        dbDelta($sql);
    }


    /**
     * TABLE: Keyword Ideas
     *
     * Stores individual keyword suggestions discovered via n8n
     * (Google Autocomplete + Related Searches + PAA).
     *
     * Design Notes:
     * - discovery_id links to apseo_keyword_discoveries
     * - source: autocomplete | related_search | people_also_ask
     * - relevance_score: 0-100 based on similarity to seed
     * - is_question: TRUE for question-type keywords (FAQ opportunities)
     * - status: active | used | dismissed
     * - keyword_hash for deduplication across discoveries
     */
    private function create_keyword_ideas_table(\wpdb $wpdb, string $charset): void {
        $table = $wpdb->prefix . 'apseo_keyword_ideas';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            discovery_id VARCHAR(64) NOT NULL,
            keyword VARCHAR(500) NOT NULL,
            keyword_hash CHAR(32) NOT NULL,
            source ENUM('autocomplete','related_search','people_also_ask') DEFAULT 'autocomplete',
            relevance_score TINYINT(3) UNSIGNED DEFAULT 50,
            search_volume_hint VARCHAR(50) DEFAULT NULL,
            is_question TINYINT(1) DEFAULT 0,
            status ENUM('active','used','dismissed') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_discovery (discovery_id),
            KEY idx_keyword_hash (keyword_hash),
            KEY idx_source (source),
            KEY idx_relevance (relevance_score DESC),
            KEY idx_status (status),
            KEY idx_question (is_question)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * ALTER existing tables for Feature 7 (E-commerce SERP Intelligence)
     *
     * Adds columns to:
     * - apseo_serp_analysis: keyword_intent, ecommerce_data
     * - apseo_serp_competitors: ecommerce_signals
     */
    private function alter_serp_tables(\wpdb $wpdb): void {
        $analysis_table = $wpdb->prefix . 'apseo_serp_analysis';
        $competitors_table = $wpdb->prefix . 'apseo_serp_competitors';

        // Check if columns already exist before adding
        $analysis_cols = $wpdb->get_col("SHOW COLUMNS FROM {$analysis_table}", 0);

        if (!in_array('keyword_intent', $analysis_cols)) {
            $wpdb->query(
                "ALTER TABLE {$analysis_table}
                 ADD COLUMN keyword_intent VARCHAR(20) DEFAULT 'informational' AFTER keyword_hash"
            );
        }

        if (!in_array('ecommerce_data', $analysis_cols)) {
            $wpdb->query(
                "ALTER TABLE {$analysis_table}
                 ADD COLUMN ecommerce_data LONGTEXT DEFAULT NULL AFTER common_questions"
            );
        }

        // Competitors table
        $comp_cols = $wpdb->get_col("SHOW COLUMNS FROM {$competitors_table}", 0);

        if (!in_array('ecommerce_signals', $comp_cols)) {
            $wpdb->query(
                "ALTER TABLE {$competitors_table}
                 ADD COLUMN ecommerce_signals LONGTEXT DEFAULT NULL AFTER schema_types"
            );
        }
    }

    /**
     * Drop V2 tables (for uninstall)
     */
    public function drop_tables(): void {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'apseo_oos_log',
            $wpdb->prefix . 'apseo_keyword_discoveries',
            $wpdb->prefix . 'apseo_keyword_ideas',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        // Note: We don't remove ALTER columns (data preservation)
    }
}
