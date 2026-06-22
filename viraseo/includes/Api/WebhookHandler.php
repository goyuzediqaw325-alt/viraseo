<?php
namespace ViraSEO\Api;

use ViraSEO\Admin\Dashboard;

class WebhookHandler {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes for n8n webhooks.
     */
    public function register_routes() {
        register_rest_route('viraseo/v1', '/gsc-data', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_gsc'],
            'permission_callback' => [$this, 'verify_secret'],
        ]);

        register_rest_route('viraseo/v1', '/serp-results', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_serp'],
            'permission_callback' => [$this, 'verify_secret'],
        ]);

        register_rest_route('viraseo/v1', '/cannibalization', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_cannibal'],
            'permission_callback' => [$this, 'verify_secret'],
        ]);

        register_rest_route('viraseo/v1', '/keyword-ideas', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_keywords'],
            'permission_callback' => [$this, 'verify_secret'],
        ]);
    }

    /**
     * Verify X-ViraSEO-Secret header against stored secret.
     */
    public function verify_secret(\WP_REST_Request $request): bool {
        $settings = Dashboard::get_settings();
        $secret = $request->get_header('X-ViraSEO-Secret');

        if (empty($settings['n8n_secret_key']) || empty($secret)) {
            return false;
        }

        return hash_equals($settings['n8n_secret_key'], $secret);
    }

    /**
     * Handle GSC data webhook: upsert keywords.
     */
    public function handle_gsc(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'viraseo_gsc_keywords';
        $data = $request->get_json_params();

        if (empty($data['keywords']) || !is_array($data['keywords'])) {
            return new \WP_REST_Response(['success' => false, 'message' => 'No keywords provided.'], 400);
        }

        $settings = Dashboard::get_settings();
        $striking_min = (int) $settings['striking_distance_min'];
        $striking_max = (int) $settings['striking_distance_max'];

        $inserted = 0;
        $updated = 0;

        foreach ($data['keywords'] as $row) {
            if (empty($row['keyword']) || empty($row['page_url']) || empty($row['date_recorded'])) {
                continue;
            }

            $keyword = sanitize_text_field($row['keyword']);
            $page_url = esc_url_raw($row['page_url']);
            $keyword_hash = md5($keyword);
            $page_url_hash = md5($page_url);
            $position = floatval($row['position'] ?? 0);
            $is_striking = ($position >= $striking_min && $position <= $striking_max) ? 1 : 0;

            $post_id = isset($row['post_id']) ? absint($row['post_id']) : url_to_postid($page_url);

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE keyword_hash = %s AND page_url_hash = %s AND date_recorded = %s",
                $keyword_hash,
                $page_url_hash,
                $row['date_recorded']
            ));

            $record = [
                'keyword' => $keyword,
                'keyword_hash' => $keyword_hash,
                'page_url' => $page_url,
                'page_url_hash' => $page_url_hash,
                'post_id' => $post_id ?: null,
                'clicks' => absint($row['clicks'] ?? 0),
                'impressions' => absint($row['impressions'] ?? 0),
                'ctr' => floatval($row['ctr'] ?? 0),
                'position' => $position,
                'date_recorded' => sanitize_text_field($row['date_recorded']),
                'is_striking_distance' => $is_striking,
            ];

            if ($existing) {
                $wpdb->update($table, $record, ['id' => $existing]);
                $updated++;
            } else {
                $record['created_at'] = current_time('mysql');
                $wpdb->insert($table, $record);
                $inserted++;
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'inserted' => $inserted,
            'updated' => $updated,
        ], 200);
    }

    /**
     * Handle SERP results webhook: update analysis record + insert competitors.
     */
    public function handle_serp(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $analysis_table = $wpdb->prefix . 'viraseo_serp_analysis';
        $competitors_table = $wpdb->prefix . 'viraseo_serp_competitors';
        $data = $request->get_json_params();

        if (empty($data['analysis_id'])) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Missing analysis_id.'], 400);
        }

        $analysis_id = absint($data['analysis_id']);

        // Update analysis record
        $update_data = [
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
        ];

        if (isset($data['keyword_intent'])) {
            $update_data['keyword_intent'] = sanitize_text_field($data['keyword_intent']);
        }
        if (isset($data['avg_content_length'])) {
            $update_data['avg_content_length'] = absint($data['avg_content_length']);
        }
        if (isset($data['avg_headings_count'])) {
            $update_data['avg_headings_count'] = absint($data['avg_headings_count']);
        }
        if (isset($data['lsi_keywords'])) {
            $update_data['lsi_keywords'] = wp_json_encode($data['lsi_keywords']);
        }
        if (isset($data['content_gap'])) {
            $update_data['content_gap'] = wp_json_encode($data['content_gap']);
        }
        if (isset($data['common_questions'])) {
            $update_data['common_questions'] = wp_json_encode($data['common_questions']);
        }
        if (isset($data['ecommerce_data'])) {
            $update_data['ecommerce_data'] = wp_json_encode($data['ecommerce_data']);
        }
        if (isset($data['n8n_execution_id'])) {
            $update_data['n8n_execution_id'] = sanitize_text_field($data['n8n_execution_id']);
        }
        if (isset($data['error_message'])) {
            $update_data['status'] = 'failed';
            $update_data['error_message'] = sanitize_text_field($data['error_message']);
        }

        $wpdb->update($analysis_table, $update_data, ['id' => $analysis_id]);

        // Insert competitors
        $competitors_inserted = 0;
        if (!empty($data['competitors']) && is_array($data['competitors'])) {
            // Remove old competitors for this analysis
            $wpdb->delete($competitors_table, ['analysis_id' => $analysis_id]);

            foreach ($data['competitors'] as $competitor) {
                $wpdb->insert($competitors_table, [
                    'analysis_id' => $analysis_id,
                    'position' => absint($competitor['position'] ?? 0),
                    'url' => esc_url_raw($competitor['url'] ?? ''),
                    'title' => sanitize_text_field($competitor['title'] ?? ''),
                    'word_count' => absint($competitor['word_count'] ?? 0),
                    'headings_structure' => isset($competitor['headings_structure'])
                        ? wp_json_encode($competitor['headings_structure']) : null,
                    'h1_count' => absint($competitor['h1_count'] ?? 0),
                    'h2_count' => absint($competitor['h2_count'] ?? 0),
                    'h3_count' => absint($competitor['h3_count'] ?? 0),
                    'internal_links_count' => absint($competitor['internal_links_count'] ?? 0),
                    'external_links_count' => absint($competitor['external_links_count'] ?? 0),
                    'images_count' => absint($competitor['images_count'] ?? 0),
                    'schema_types' => sanitize_text_field($competitor['schema_types'] ?? ''),
                    'ecommerce_signals' => isset($competitor['ecommerce_signals'])
                        ? wp_json_encode($competitor['ecommerce_signals']) : null,
                    'domain' => sanitize_text_field($competitor['domain'] ?? ''),
                ]);
                $competitors_inserted++;
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'analysis_id' => $analysis_id,
            'competitors_inserted' => $competitors_inserted,
        ], 200);
    }

    /**
     * Handle cannibalization webhook: insert new conflicts (skip existing unresolved).
     */
    public function handle_cannibal(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'viraseo_cannibalization';
        $data = $request->get_json_params();

        if (empty($data['conflicts']) || !is_array($data['conflicts'])) {
            return new \WP_REST_Response(['success' => false, 'message' => 'No conflicts provided.'], 400);
        }

        $inserted = 0;
        $skipped = 0;

        foreach ($data['conflicts'] as $conflict) {
            if (empty($conflict['keyword']) || empty($conflict['page_url_1']) || empty($conflict['page_url_2'])) {
                continue;
            }

            $keyword = sanitize_text_field($conflict['keyword']);
            $keyword_hash = md5($keyword);

            // Check if an unresolved conflict already exists for this keyword
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE keyword_hash = %s AND status IN ('detected', 'reviewing') LIMIT 1",
                $keyword_hash
            ));

            if ($existing) {
                $skipped++;
                continue;
            }

            $wpdb->insert($table, [
                'keyword' => $keyword,
                'keyword_hash' => $keyword_hash,
                'page_url_1' => esc_url_raw($conflict['page_url_1']),
                'post_id_1' => isset($conflict['post_id_1']) ? absint($conflict['post_id_1']) : null,
                'position_1' => floatval($conflict['position_1'] ?? 0),
                'impressions_1' => absint($conflict['impressions_1'] ?? 0),
                'page_url_2' => esc_url_raw($conflict['page_url_2']),
                'post_id_2' => isset($conflict['post_id_2']) ? absint($conflict['post_id_2']) : null,
                'position_2' => floatval($conflict['position_2'] ?? 0),
                'impressions_2' => absint($conflict['impressions_2'] ?? 0),
                'severity' => in_array($conflict['severity'] ?? '', ['critical', 'warning', 'info'])
                    ? $conflict['severity'] : 'warning',
                'recommended_action' => sanitize_textarea_field($conflict['recommended_action'] ?? ''),
                'status' => 'detected',
                'detected_at' => current_time('mysql'),
            ]);
            $inserted++;
        }

        return new \WP_REST_Response([
            'success' => true,
            'inserted' => $inserted,
            'skipped' => $skipped,
        ], 200);
    }

    /**
     * Handle keyword ideas webhook: insert ideas + update discovery status.
     */
    public function handle_keywords(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $ideas_table = $wpdb->prefix . 'viraseo_keyword_ideas';
        $discoveries_table = $wpdb->prefix . 'viraseo_keyword_discoveries';
        $data = $request->get_json_params();

        if (empty($data['discovery_id'])) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Missing discovery_id.'], 400);
        }

        $discovery_id = sanitize_text_field($data['discovery_id']);
        $inserted = 0;

        if (!empty($data['ideas']) && is_array($data['ideas'])) {
            foreach ($data['ideas'] as $idea) {
                if (empty($idea['keyword'])) {
                    continue;
                }

                $keyword = sanitize_text_field($idea['keyword']);

                $wpdb->insert($ideas_table, [
                    'discovery_id' => $discovery_id,
                    'keyword' => $keyword,
                    'keyword_hash' => md5($keyword),
                    'source' => in_array($idea['source'] ?? '', ['autocomplete', 'related_search', 'people_also_ask'])
                        ? $idea['source'] : 'autocomplete',
                    'relevance_score' => min(100, max(0, absint($idea['relevance_score'] ?? 0))),
                    'is_question' => !empty($idea['is_question']) ? 1 : 0,
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                ]);
                $inserted++;
            }
        }

        // Update discovery status to completed
        $wpdb->update($discoveries_table, [
            'status' => 'completed',
            'ideas_count' => $inserted,
            'completed_at' => current_time('mysql'),
        ], ['discovery_id' => $discovery_id]);

        return new \WP_REST_Response([
            'success' => true,
            'discovery_id' => $discovery_id,
            'ideas_inserted' => $inserted,
        ], 200);
    }

    /**
     * Trigger GSC sync via n8n webhook.
     */
    public static function trigger_gsc_sync(): array {
        $settings = Dashboard::get_settings();

        if (empty($settings['n8n_webhook_base_url'])) {
            return ['success' => false, 'message' => 'آدرس webhook تنظیم نشده است.'];
        }

        $url = trailingslashit($settings['n8n_webhook_base_url']) . 'gsc-sync';
        $callback_url = rest_url('viraseo/v1/gsc-data');
        $cannibal_url = rest_url('viraseo/v1/cannibalization');

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-ViraSEO-Secret' => $settings['n8n_secret_key'],
            ],
            'body' => wp_json_encode([
                'callback_url' => $callback_url,
                'cannibal_url' => $cannibal_url,
                'site_url' => home_url(),
                'striking_distance_min' => (int) $settings['striking_distance_min'],
                'striking_distance_max' => (int) $settings['striking_distance_max'],
                'min_impressions' => (int) $settings['min_impressions_threshold'],
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'data' => $body];
        }

        return ['success' => false, 'message' => 'HTTP ' . $code, 'data' => $body];
    }

    /**
     * Send SERP analysis request to n8n.
     */
    public static function send_serp_request(string $keyword, int $user_id = 0): array {
        global $wpdb;
        $settings = Dashboard::get_settings();

        if (empty($settings['n8n_webhook_base_url'])) {
            return ['success' => false, 'message' => 'آدرس webhook تنظیم نشده است.'];
        }

        // Create analysis record
        $table = $wpdb->prefix . 'viraseo_serp_analysis';
        $keyword_hash = md5($keyword);

        $wpdb->insert($table, [
            'keyword' => $keyword,
            'keyword_hash' => $keyword_hash,
            'requested_by' => $user_id ?: get_current_user_id(),
            'status' => 'pending',
            'requested_at' => current_time('mysql'),
        ]);

        $analysis_id = $wpdb->insert_id;

        if (!$analysis_id) {
            return ['success' => false, 'message' => 'خطا در ایجاد رکورد تحلیل.'];
        }

        // Send request to n8n
        $url = trailingslashit($settings['n8n_webhook_base_url']) . 'serp-analyze';
        $callback_url = rest_url('viraseo/v1/serp-results');

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-ViraSEO-Secret' => $settings['n8n_secret_key'],
            ],
            'body' => wp_json_encode([
                'keyword' => $keyword,
                'analysis_id' => $analysis_id,
                'callback_url' => $callback_url,
                'site_url' => home_url(),
            ]),
        ]);

        if (is_wp_error($response)) {
            // Mark as failed
            $wpdb->update($table, [
                'status' => 'failed',
                'error_message' => $response->get_error_message(),
            ], ['id' => $analysis_id]);

            return ['success' => false, 'message' => $response->get_error_message(), 'analysis_id' => $analysis_id];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            // Mark as processing
            $wpdb->update($table, ['status' => 'processing'], ['id' => $analysis_id]);
            return ['success' => true, 'analysis_id' => $analysis_id];
        }

        // Mark as failed
        $wpdb->update($table, [
            'status' => 'failed',
            'error_message' => 'HTTP ' . $code,
        ], ['id' => $analysis_id]);

        return ['success' => false, 'message' => 'HTTP ' . $code, 'analysis_id' => $analysis_id];
    }
}
