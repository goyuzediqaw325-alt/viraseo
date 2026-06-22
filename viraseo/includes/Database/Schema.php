<?php
namespace ViraSEO\Database;
defined('ABSPATH') || exit;

class Schema {
    public function create_all_tables(): void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $p = $wpdb->prefix . 'viraseo_';

        dbDelta("CREATE TABLE {$p}gsc_keywords (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(500) NOT NULL,
            keyword_hash char(32) NOT NULL,
            page_url varchar(2048) NOT NULL,
            page_url_hash char(32) NOT NULL,
            post_id bigint unsigned DEFAULT NULL,
            clicks int unsigned DEFAULT 0,
            impressions int unsigned DEFAULT 0,
            ctr decimal(5,4) DEFAULT 0,
            position decimal(5,2) DEFAULT 0,
            date_recorded date NOT NULL,
            is_striking tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_kpd (keyword_hash,page_url_hash,date_recorded),
            KEY idx_pos (position),
            KEY idx_imp (impressions)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}cannibalization (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(500) NOT NULL,
            keyword_hash char(32) NOT NULL,
            page_url_1 varchar(2048) NOT NULL,
            position_1 decimal(5,2) DEFAULT 0,
            impressions_1 int unsigned DEFAULT 0,
            page_url_2 varchar(2048) NOT NULL,
            position_2 decimal(5,2) DEFAULT 0,
            impressions_2 int unsigned DEFAULT 0,
            severity enum('critical','warning','info') DEFAULT 'info',
            recommended_action varchar(255) DEFAULT NULL,
            status enum('detected','resolved','ignored') DEFAULT 'detected',
            detected_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sev (severity),
            KEY idx_st (status)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}serp_analysis (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(500) NOT NULL,
            keyword_hash char(32) NOT NULL,
            status enum('pending','processing','completed','failed') DEFAULT 'pending',
            avg_word_count int unsigned DEFAULT 0,
            avg_headings int unsigned DEFAULT 0,
            lsi_keywords longtext DEFAULT NULL,
            content_gap longtext DEFAULT NULL,
            questions longtext DEFAULT NULL,
            ecommerce_data longtext DEFAULT NULL,
            requested_by bigint unsigned DEFAULT NULL,
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_st (status)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}serp_competitors (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            analysis_id bigint unsigned NOT NULL,
            position tinyint unsigned NOT NULL,
            url varchar(2048) NOT NULL,
            title varchar(500) DEFAULT NULL,
            domain varchar(255) DEFAULT NULL,
            word_count int unsigned DEFAULT 0,
            h1_count tinyint unsigned DEFAULT 0,
            h2_count tinyint unsigned DEFAULT 0,
            h3_count tinyint unsigned DEFAULT 0,
            images_count smallint unsigned DEFAULT 0,
            schema_types varchar(500) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_aid (analysis_id)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}internal_links (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            source_id bigint unsigned NOT NULL,
            target_id bigint unsigned NOT NULL,
            anchor varchar(500) DEFAULT NULL,
            link_url varchar(2048) NOT NULL,
            found_in enum('content','menu','widget') DEFAULT 'content',
            crawled_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_src (source_id),
            KEY idx_tgt (target_id)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}orphan_pages (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint unsigned NOT NULL,
            post_type varchar(50) NOT NULL,
            post_title varchar(500) DEFAULT NULL,
            inlinks int unsigned DEFAULT 0,
            outlinks int unsigned DEFAULT 0,
            status enum('orphan','low','ok') DEFAULT 'orphan',
            detected_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_post (post_id)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}link_suggestions (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            source_id bigint unsigned NOT NULL,
            target_id bigint unsigned NOT NULL,
            anchor varchar(500) DEFAULT NULL,
            score decimal(5,2) DEFAULT 0,
            reason text DEFAULT NULL,
            status enum('pending','accepted','rejected') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pair (source_id,target_id)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}backlinks (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            source_url varchar(2048) NOT NULL,
            source_domain varchar(255) NOT NULL,
            target_url varchar(2048) NOT NULL,
            anchor varchar(500) DEFAULT NULL,
            link_type enum('reporataj','guest','directory','exchange','other') DEFAULT 'other',
            cost decimal(12,0) DEFAULT 0,
            dofollow tinyint(1) DEFAULT 1,
            da tinyint unsigned DEFAULT 0,
            spam_score tinyint unsigned DEFAULT 0,
            link_status enum('live','dead','pending') DEFAULT 'pending',
            date_acquired date DEFAULT NULL,
            date_jalali varchar(10) DEFAULT NULL,
            contact varchar(255) DEFAULT NULL,
            notes text DEFAULT NULL,
            created_by bigint unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_dom (source_domain),
            KEY idx_st (link_status)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}disavow (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            entry varchar(500) NOT NULL,
            entry_type enum('domain','url') DEFAULT 'domain',
            reason varchar(500) DEFAULT NULL,
            added_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_entry (entry(191))
        ) {$c};");

        dbDelta("CREATE TABLE {$p}oos_log (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint unsigned NOT NULL,
            title varchar(500) DEFAULT NULL,
            has_traffic tinyint(1) DEFAULT 0,
            action_taken varchar(50) DEFAULT 'pending',
            detected_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_prod (product_id)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}keyword_discoveries (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            discovery_id varchar(64) NOT NULL,
            seed varchar(500) NOT NULL,
            status enum('processing','completed','failed') DEFAULT 'processing',
            ideas_count int unsigned DEFAULT 0,
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_disc (discovery_id)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}keyword_ideas (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            discovery_id varchar(64) NOT NULL,
            keyword varchar(500) NOT NULL,
            source enum('suggest','related','paa') DEFAULT 'suggest',
            relevance tinyint unsigned DEFAULT 50,
            is_question tinyint(1) DEFAULT 0,
            status enum('active','used','dismissed') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_disc (discovery_id),
            KEY idx_st (status)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}activity_log (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            action varchar(100) NOT NULL,
            detail text DEFAULT NULL,
            user_id bigint unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_act (action)
        ) {$c};");
    }
}
