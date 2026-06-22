<?php
/**
 * Feature 8: Organic Traffic ROI Forecaster
 *
 * Uses existing GSC data to predict traffic growth potential:
 * 1. Takes "Striking Distance" keywords (positions 11-30)
 * 2. Applies a standard SEO CTR Curve model per target rank
 * 3. Calculates: (Impressions × Target CTR) - Current Clicks = Potential Growth
 * 4. Displays as a motivational Persian dashboard sorted by highest potential
 *
 * CTR Model Source: Industry-standard organic CTR curves (Advanced Web Ranking, Backlinko)
 * Adjusted for Persian SERPs (slightly lower CTR due to featured snippets in FA results)
 *
 * NO external API calls — uses only existing GSC data from apseo_gsc_keywords table
 *
 * @package AdvancedPersianSEO\Features
 */

namespace APSEO\Features;

defined('ABSPATH') || exit;

use APSEO\Admin\Dashboard;
use APSEO\Utils\JalaliDate;
use APSEO\Utils\PersianText;

class TrafficForecaster {

    /**
     * Standard Organic CTR Curve by Position
     *
     * Based on aggregated industry data:
     * - Position 1: ~31.7% CTR
     * - Position 2: ~24.7%
     * - Position 3: ~18.6%
     * - Positions 4-10: declining curve
     * - Positions 11+: ~1-3%
     *
     * These are approximations; actual CTR varies by query type and SERP features.
     */
    private const CTR_CURVE = [
        1  => 0.317,
        2  => 0.247,
        3  => 0.186,
        4  => 0.132,
        5  => 0.095,
        6  => 0.063,
        7  => 0.044,
        8  => 0.033,
        9  => 0.028,
        10 => 0.025,
        11 => 0.022,
        12 => 0.019,
        13 => 0.016,
        14 => 0.014,
        15 => 0.012,
        16 => 0.010,
        17 => 0.009,
        18 => 0.008,
        19 => 0.007,
        20 => 0.006,
        21 => 0.005,
        22 => 0.005,
        23 => 0.004,
        24 => 0.004,
        25 => 0.004,
        26 => 0.003,
        27 => 0.003,
        28 => 0.003,
        29 => 0.003,
        30 => 0.003,
    ];

    /**
     * Default target positions for forecasting scenarios
     */
    private const FORECAST_TARGETS = [
        'optimistic'  => 3,  // Target: Position 3
        'moderate'    => 5,  // Target: Position 5
        'conservative' => 8, // Target: Position 8
    ];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_apseo_get_traffic_forecast', [$this, 'ajax_get_forecast']);
        add_action('wp_ajax_apseo_get_forecast_summary', [$this, 'ajax_get_summary']);
        add_action('wp_ajax_apseo_calculate_keyword_roi', [$this, 'ajax_calculate_roi']);
    }

    /**
     * Get expected CTR for a given position
     *
     * @param float $position The ranking position (can be decimal like 11.3)
     * @return float Expected CTR (0.0 to 1.0)
     */
    public static function get_ctr_for_position(float $position): float {
        // Round to nearest integer position
        $pos = max(1, min(30, (int) round($position)));

        return self::CTR_CURVE[$pos] ?? 0.003;
    }

    /**
     * Calculate traffic potential for a single keyword
     *
     * Formula:
     *   Potential Monthly Traffic = Impressions × Target Position CTR
     *   Traffic Growth = Potential Traffic - Current Clicks
     *   Growth Percentage = (Growth / max(Current Clicks, 1)) × 100
     *
     * @param int   $impressions     Monthly impressions from GSC
     * @param int   $current_clicks  Current monthly clicks
     * @param float $current_position Current average position
     * @param int   $target_position  Desired target position
     * @return array Forecast data
     */
    public static function calculate_keyword_forecast(
        int $impressions,
        int $current_clicks,
        float $current_position,
        int $target_position = 5
    ): array {
        $current_ctr = $impressions > 0 ? $current_clicks / $impressions : 0;
        $target_ctr = self::get_ctr_for_position((float) $target_position);

        $potential_clicks = (int) round($impressions * $target_ctr);
        $traffic_growth = max(0, $potential_clicks - $current_clicks);
        $growth_percentage = $current_clicks > 0
            ? round(($traffic_growth / $current_clicks) * 100, 1)
            : ($potential_clicks > 0 ? 999 : 0); // Cap at 999% for zero-click keywords

        // Effort score: How hard is it to reach target?
        // Lower current position + higher impressions = lower effort
        $position_gap = $current_position - $target_position;
        $effort_score = self::calculate_effort_score($position_gap, $impressions, $current_clicks);

        // ROI score: Traffic Growth weighted by ease (higher = better opportunity)
        $roi_score = $traffic_growth * (100 / max($effort_score, 1));

        return [
            'impressions'        => $impressions,
            'current_clicks'     => $current_clicks,
            'current_position'   => $current_position,
            'current_ctr'        => round($current_ctr * 100, 2),
            'target_position'    => $target_position,
            'target_ctr'         => round($target_ctr * 100, 2),
            'potential_clicks'   => $potential_clicks,
            'traffic_growth'     => $traffic_growth,
            'growth_percentage'  => min($growth_percentage, 999),
            'effort_score'       => $effort_score,
            'roi_score'          => round($roi_score, 2),
        ];
    }

    /**
     * Calculate effort score (1-100, lower = easier)
     *
     * Factors:
     * - Position gap to target (larger gap = more effort)
     * - Current impressions (more impressions = topic has demand)
     * - Current clicks (some clicks = page already has relevance)
     */
    private static function calculate_effort_score(float $position_gap, int $impressions, int $clicks): int {
        // Base effort from position gap (0-50 scale)
        $gap_effort = min(50, $position_gap * 4);

        // Demand modifier: High impressions reduce perceived effort (page topic is proven)
        $demand_modifier = 0;
        if ($impressions > 1000) $demand_modifier = -15;
        elseif ($impressions > 500) $demand_modifier = -10;
        elseif ($impressions > 100) $demand_modifier = -5;

        // Relevance modifier: Some clicks mean page is somewhat relevant already
        $relevance_modifier = 0;
        if ($clicks > 50) $relevance_modifier = -10;
        elseif ($clicks > 10) $relevance_modifier = -5;

        $score = $gap_effort + 30 + $demand_modifier + $relevance_modifier;

        return max(1, min(100, (int) round($score)));
    }

    /**
     * AJAX: Get full traffic forecast for striking distance keywords
     */
    public function ajax_get_forecast(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'apseo_gsc_keywords';
        $settings = Dashboard::get_settings();

        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = 25;
        $offset = ($page - 1) * $per_page;
        $target_position = absint($_POST['target_position'] ?? 5);
        $sort_by = sanitize_text_field($_POST['sort_by'] ?? 'traffic_growth');
        $min_position = absint($_POST['min_position'] ?? 11);
        $max_position = absint($_POST['max_position'] ?? 30);
        $min_impressions = absint($_POST['min_impressions'] ?? $settings['min_impressions_threshold']);

        // Get aggregated data per keyword (latest 30 days)
        $keywords = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                keyword,
                page_url,
                post_id,
                SUM(clicks) as total_clicks,
                SUM(impressions) as total_impressions,
                AVG(position) as avg_position,
                AVG(ctr) as avg_ctr
             FROM {$table}
             WHERE date_recorded >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               AND position BETWEEN %f AND %f
               AND impressions >= %d
             GROUP BY keyword_hash, page_url_hash
             HAVING total_impressions >= %d
             ORDER BY total_impressions DESC
             LIMIT 500",
            (float) $min_position,
            (float) $max_position,
            1, // At least 1 impression per day entry
            $min_impressions
        ));

        // Calculate forecasts
        $forecasts = [];
        foreach ($keywords as $kw) {
            $forecast = self::calculate_keyword_forecast(
                (int) $kw->total_impressions,
                (int) $kw->total_clicks,
                (float) $kw->avg_position,
                $target_position
            );

            $forecasts[] = array_merge($forecast, [
                'keyword'    => $kw->keyword,
                'page_url'   => $kw->page_url,
                'post_id'    => $kw->post_id,
                'post_title' => $kw->post_id ? get_the_title($kw->post_id) : '',
            ]);
        }

        // Sort by chosen metric
        usort($forecasts, function ($a, $b) use ($sort_by) {
            return ($b[$sort_by] ?? 0) <=> ($a[$sort_by] ?? 0);
        });

        // Paginate
        $total = count($forecasts);
        $paged_forecasts = array_slice($forecasts, $offset, $per_page);

        // Format for Persian display
        $formatted = array_map(function ($f) {
            return [
                'keyword'           => $f['keyword'],
                'page_url'          => $f['page_url'],
                'post_id'           => $f['post_id'],
                'post_title'        => $f['post_title'],
                'impressions'       => PersianText::format_number($f['impressions']),
                'current_clicks'    => PersianText::format_number($f['current_clicks']),
                'current_position'  => JalaliDate::to_persian_digits(number_format($f['current_position'], 1)),
                'current_ctr'       => JalaliDate::to_persian_digits($f['current_ctr']) . '%',
                'target_position'   => JalaliDate::to_persian_digits($f['target_position']),
                'target_ctr'        => JalaliDate::to_persian_digits($f['target_ctr']) . '%',
                'potential_clicks'  => PersianText::format_number($f['potential_clicks']),
                'traffic_growth'    => PersianText::format_number($f['traffic_growth']),
                'traffic_growth_raw' => $f['traffic_growth'],
                'growth_percentage' => JalaliDate::to_persian_digits($f['growth_percentage']) . '%',
                'effort_score'      => $f['effort_score'],
                'effort_label'      => self::get_effort_label($f['effort_score']),
                'roi_score'         => JalaliDate::to_persian_digits(number_format($f['roi_score'], 0)),
                'roi_score_raw'     => $f['roi_score'],
                'priority_label'    => self::get_priority_label($f['roi_score'], $f['traffic_growth']),
            ];
        }, $paged_forecasts);

        wp_send_json_success([
            'forecasts'       => $formatted,
            'total'           => $total,
            'pages'           => ceil($total / $per_page),
            'current_page'    => $page,
            'target_position' => $target_position,
        ]);
    }

    /**
     * AJAX: Get forecast summary statistics
     */
    public function ajax_get_summary(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('دسترسی غیرمجاز.', 'advanced-persian-seo'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'apseo_gsc_keywords';
        $settings = Dashboard::get_settings();

        // Get all striking distance keywords
        $keywords = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                SUM(clicks) as total_clicks,
                SUM(impressions) as total_impressions,
                AVG(position) as avg_position
             FROM {$table}
             WHERE date_recorded >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               AND position BETWEEN %d AND %d
               AND impressions >= %d
             GROUP BY keyword_hash, page_url_hash",
            $settings['striking_distance_min'] ?? 11,
            30,
            $settings['min_impressions_threshold'] ?? 10
        ));

        // Calculate totals across all scenarios
        $total_potential = ['optimistic' => 0, 'moderate' => 0, 'conservative' => 0];
        $total_current_clicks = 0;

        foreach ($keywords as $kw) {
            $total_current_clicks += (int) $kw->total_clicks;

            foreach (self::FORECAST_TARGETS as $scenario => $target) {
                $forecast = self::calculate_keyword_forecast(
                    (int) $kw->total_impressions,
                    (int) $kw->total_clicks,
                    (float) $kw->avg_position,
                    $target
                );
                $total_potential[$scenario] += $forecast['traffic_growth'];
            }
        }

        wp_send_json_success([
            'total_keywords'     => count($keywords),
            'total_keywords_fa'  => PersianText::format_number(count($keywords)),
            'current_monthly_clicks' => PersianText::format_number($total_current_clicks),
            'scenarios'          => [
                'optimistic' => [
                    'target'   => self::FORECAST_TARGETS['optimistic'],
                    'label'    => __('خوش‌بینانه (جایگاه ۳)', 'advanced-persian-seo'),
                    'growth'   => PersianText::format_number($total_potential['optimistic']),
                    'growth_raw' => $total_potential['optimistic'],
                ],
                'moderate' => [
                    'target'   => self::FORECAST_TARGETS['moderate'],
                    'label'    => __('متعادل (جایگاه ۵)', 'advanced-persian-seo'),
                    'growth'   => PersianText::format_number($total_potential['moderate']),
                    'growth_raw' => $total_potential['moderate'],
                ],
                'conservative' => [
                    'target'   => self::FORECAST_TARGETS['conservative'],
                    'label'    => __('محتاطانه (جایگاه ۸)', 'advanced-persian-seo'),
                    'growth'   => PersianText::format_number($total_potential['conservative']),
                    'growth_raw' => $total_potential['conservative'],
                ],
            ],
            'ctr_curve' => self::get_ctr_curve_for_chart(),
        ]);
    }

    /**
     * AJAX: Calculate ROI for a specific keyword with custom target
     */
    public function ajax_calculate_roi(): void {
        check_ajax_referer('apseo_admin_nonce', 'nonce');

        $impressions = absint($_POST['impressions'] ?? 0);
        $clicks = absint($_POST['clicks'] ?? 0);
        $position = floatval($_POST['position'] ?? 15);
        $target = absint($_POST['target_position'] ?? 5);

        if ($impressions === 0) {
            wp_send_json_error(__('تعداد نمایش صفر است.', 'advanced-persian-seo'));
        }

        $forecast = self::calculate_keyword_forecast($impressions, $clicks, $position, $target);

        // Add multi-scenario breakdown
        $scenarios = [];
        for ($t = 1; $t <= 10; $t++) {
            $s = self::calculate_keyword_forecast($impressions, $clicks, $position, $t);
            $scenarios[] = [
                'position'       => $t,
                'expected_ctr'   => $s['target_ctr'],
                'potential_clicks' => $s['potential_clicks'],
                'growth'         => $s['traffic_growth'],
            ];
        }

        $forecast['scenarios'] = $scenarios;

        wp_send_json_success($forecast);
    }

    /**
     * Get effort label in Persian
     */
    private static function get_effort_label(int $score): string {
        if ($score <= 25) return __('آسان', 'advanced-persian-seo');
        if ($score <= 50) return __('متوسط', 'advanced-persian-seo');
        if ($score <= 75) return __('سخت', 'advanced-persian-seo');
        return __('بسیار سخت', 'advanced-persian-seo');
    }

    /**
     * Get priority label based on ROI score and growth
     */
    private static function get_priority_label(float $roi_score, int $growth): string {
        if ($roi_score > 500 && $growth > 100) {
            return __('🔥 اولویت بالا', 'advanced-persian-seo');
        }
        if ($roi_score > 200 && $growth > 50) {
            return __('⭐ اولویت متوسط', 'advanced-persian-seo');
        }
        if ($roi_score > 50) {
            return __('💡 فرصت', 'advanced-persian-seo');
        }
        return __('📋 رصد', 'advanced-persian-seo');
    }

    /**
     * Get CTR curve data for Chart.js visualization
     */
    private static function get_ctr_curve_for_chart(): array {
        $labels = [];
        $values = [];

        foreach (self::CTR_CURVE as $pos => $ctr) {
            $labels[] = (string) $pos;
            $values[] = round($ctr * 100, 1);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }
}
