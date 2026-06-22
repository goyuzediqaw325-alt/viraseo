<?php
namespace ViraSEO\Database;

class Schema {

    public static function create_all_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'viraseo_';

        self::create_gsc_keywords_table($prefix, $charset_collate);
        self::create_cannibalization_table($prefix, $charset_collate);
        self::create_serp_analysis_table($prefix, $charset_collate);
        self::create_serp_competitors_table($prefix, $charset_collate);
        self::create_internal_links_table($prefix, $charset_collate);
        self::create_orphan_pages_table($prefix, $charset_collate);
        self::create_link_suggestions_table($prefix, $charset_collate);
        self::create_backlinks_table($prefix, $charset_collate);
        self::create_disavow_domains_table($prefix, $charset_collate);
        self::create_oos_log_table($prefix, $charset_collate);
        self::create_keyword_discoveries_table($prefix, $charset_collate);
        self::create_keyword_ideas_table($prefix, $charset_collate);
        self::create_activity_log_table($prefix, $charset_collate);
    }

    private static function create_gsc_keywords_table($prefix, $charset_collate) {
        $table = $prefix . 'gsc_keywords';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(500) NOT NULL,
            keyword_hash char(32) NOT NULL,
            page_url varchar(2048) NOT NULL,
            page_url_hash char(32) NOT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            clicks int(11) NOT NULL DEFAULT 0,
            impressions int(11) NOT NULL DEFAULT 0,
            ctr decimal(5,4) NOT NULL DEFAULT 0.0000,
            position decimal(5,2) NOT NULL DEFAULT 0.00,
            date_recorded date NOT NULL,
            is_striking_distance tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_keyword_page_date (keyword_hash, page_url_hash, date_recorded),
            KEY idx_keyword_hash (keyword_hash),
            KEY idx_page_url_hash (page_url_hash),
            KEY idx_date_recorded (date_recorded),
            KEY idx_striking_distance (is_striking_distance)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_cannibalization_table($prefix, $charset_collate) {
        $table = $prefix . 'cannibalization';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(500) NOT NULL,
            keyword_hash char(32) NOT NULL,
            page_url_1 varchar(2048) NOT NULL,
            post_id_1 bigint(20) unsigned DEFAULT NULL,
            position_1 decimal(5,2) NOT NULL DEFAULT 0.00,
            impressions_1 int(11) NOT NULL DEFAULT 0,
            page_url_2 varchar(2048) NOT NULL,
            post_id_2 bigint(20) unsigned DEFAULT NULL,
            position_2 decimal(5,2) NOT NULL DEFAULT 0.00,
            impressions_2 int(11) NOT NULL DEFAULT 0,
            severity enum('critical','warning','info') NOT NULL DEFAULT 'warning',
            recommended_action text DEFAULT NULL,
            status enum('detected','reviewing','resolved','ignored') NOT NULL DEFAULT 'detected',
            detected_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_keyword_hash (keyword_hash),
            KEY idx_status (status),
            KEY idx_severity (severity)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_serp_analysis_table($prefix, $charset_collate) {
        $table = $prefix . 'serp_analysis';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(500) NOT NULL,
            keyword_hash char(32) NOT NULL,
            keyword_intent varchar(20) NOT NULL DEFAULT 'informational',
            requested_by bigint(20) unsigned DEFAULT NULL,
            status enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
            avg_content_length int(11) DEFAULT NULL,
            avg_headings_count int(11) DEFAULT NULL,
            lsi_keywords longtext DEFAULT NULL,
            content_gap longtext DEFAULT NULL,
            common_questions longtext DEFAULT NULL,
            ecommerce_data longtext DEFAULT NULL,
            n8n_execution_id varchar(255) DEFAULT NULL,
            error_message text DEFAULT NULL,
            requested_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_keyword_hash (keyword_hash),
            KEY idx_status (status)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_serp_competitors_table($prefix, $charset_collate) {
        $table = $prefix . 'serp_competitors';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            analysis_id bigint(20) unsigned NOT NULL,
            position tinyint(3) unsigned NOT NULL,
            url varchar(2048) NOT NULL,
            title varchar(500) DEFAULT NULL,
            word_count int(11) DEFAULT NULL,
            headings_structure longtext DEFAULT NULL,
            h1_count smallint(5) unsigned DEFAULT 0,
            h2_count smallint(5) unsigned DEFAULT 0,
            h3_count smallint(5) unsigned DEFAULT 0,
            internal_links_count int(11) DEFAULT 0,
            external_links_count int(11) DEFAULT 0,
            images_count int(11) DEFAULT 0,
            schema_types varchar(500) DEFAULT NULL,
            ecommerce_signals longtext DEFAULT NULL,
            domain varchar(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_analysis_id (analysis_id)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_internal_links_table($prefix, $charset_collate) {
        $table = $prefix . 'internal_links';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_post_id bigint(20) unsigned NOT NULL,
            target_post_id bigint(20) unsigned NOT NULL,
            anchor_text varchar(500) DEFAULT NULL,
            link_url varchar(2048) NOT NULL,
            is_nofollow tinyint(1) NOT NULL DEFAULT 0,
            found_in enum('content','sidebar','footer','menu') NOT NULL DEFAULT 'content',
            last_crawled datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source_post (source_post_id),
            KEY idx_target_post (target_post_id)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_orphan_pages_table($prefix, $charset_collate) {
        $table = $prefix . 'orphan_pages';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            post_type varchar(50) NOT NULL DEFAULT 'post',
            post_title varchar(500) DEFAULT NULL,
            permalink varchar(2048) DEFAULT NULL,
            inlinks_count int(11) NOT NULL DEFAULT 0,
            outlinks_count int(11) NOT NULL DEFAULT 0,
            status enum('orphan','low_links','resolved') NOT NULL DEFAULT 'orphan',
            detected_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_post_id (post_id),
            KEY idx_status (status)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_link_suggestions_table($prefix, $charset_collate) {
        $table = $prefix . 'link_suggestions';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_post_id bigint(20) unsigned NOT NULL,
            target_post_id bigint(20) unsigned NOT NULL,
            suggested_anchor varchar(500) DEFAULT NULL,
            relevance_score decimal(5,2) NOT NULL DEFAULT 0.00,
            cluster_id bigint(20) unsigned DEFAULT NULL,
            reason text DEFAULT NULL,
            status enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_source_target (source_post_id, target_post_id),
            KEY idx_status (status),
            KEY idx_cluster_id (cluster_id)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_backlinks_table($prefix, $charset_collate) {
        $table = $prefix . 'backlinks';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_url varchar(2048) NOT NULL,
            source_domain varchar(255) NOT NULL,
            target_url varchar(2048) NOT NULL,
            target_post_id bigint(20) unsigned DEFAULT NULL,
            anchor_text varchar(500) DEFAULT NULL,
            link_type enum('reporataj','guest_post','directory','comment','social','exchange','other') NOT NULL DEFAULT 'other',
            cost decimal(12,0) NOT NULL DEFAULT 0,
            currency enum('toman','rial','usd','eur') NOT NULL DEFAULT 'toman',
            dofollow tinyint(1) NOT NULL DEFAULT 1,
            domain_authority tinyint(3) unsigned DEFAULT NULL,
            spam_score tinyint(3) unsigned DEFAULT NULL,
            link_status enum('live','dead','pending','removed') NOT NULL DEFAULT 'pending',
            date_acquired date DEFAULT NULL,
            date_acquired_jalali varchar(10) DEFAULT NULL,
            contact_name varchar(255) DEFAULT NULL,
            notes text DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source_domain (source_domain),
            KEY idx_link_status (link_status),
            KEY idx_link_type (link_type)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_disavow_domains_table($prefix, $charset_collate) {
        $table = $prefix . 'disavow_domains';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            domain_or_url varchar(191) NOT NULL,
            disavow_type enum('domain','url') NOT NULL DEFAULT 'domain',
            reason text DEFAULT NULL,
            spam_score tinyint(3) unsigned DEFAULT NULL,
            source enum('manual','auto_detected','imported') NOT NULL DEFAULT 'manual',
            added_by bigint(20) unsigned DEFAULT NULL,
            added_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_domain_or_url (domain_or_url)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_oos_log_table($prefix, $charset_collate) {
        $table = $prefix . 'oos_log';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            product_title varchar(500) DEFAULT NULL,
            has_traffic tinyint(1) NOT NULL DEFAULT 0,
            action_taken enum('show_alternatives','redirected_301','pending_review') NOT NULL DEFAULT 'pending_review',
            redirect_url varchar(2048) DEFAULT NULL,
            detected_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_product_id (product_id)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_keyword_discoveries_table($prefix, $charset_collate) {
        $table = $prefix . 'keyword_discoveries';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            discovery_id varchar(64) NOT NULL,
            seed_keyword varchar(500) NOT NULL,
            status enum('processing','completed','failed') NOT NULL DEFAULT 'processing',
            ideas_count int(11) NOT NULL DEFAULT 0,
            requested_by bigint(20) unsigned DEFAULT NULL,
            requested_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_discovery_id (discovery_id)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_keyword_ideas_table($prefix, $charset_collate) {
        $table = $prefix . 'keyword_ideas';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            discovery_id varchar(64) NOT NULL,
            keyword varchar(500) NOT NULL,
            keyword_hash char(32) NOT NULL,
            source enum('autocomplete','related_search','people_also_ask') NOT NULL DEFAULT 'autocomplete',
            relevance_score tinyint(3) unsigned NOT NULL DEFAULT 0,
            is_question tinyint(1) NOT NULL DEFAULT 0,
            status enum('active','used','dismissed') NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_discovery_id (discovery_id),
            KEY idx_keyword_hash (keyword_hash),
            KEY idx_status (status)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private static function create_activity_log_table($prefix, $charset_collate) {
        $table = $prefix . 'activity_log';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action_type varchar(100) NOT NULL,
            description text DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            context longtext DEFAULT NULL,
            jalali_date varchar(10) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_action_type (action_type),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";
        dbDelta($sql);
    }
}
