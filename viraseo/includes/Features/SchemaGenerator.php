<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

use ViraSEO\Utils\PersianText;

class SchemaGenerator {

    const META_DISABLED = '_viraseo_schema_disabled';
    const META_CUSTOM   = '_viraseo_custom_schema';
    const OPTION_KEY    = 'viraseo_schema_settings';

    public function __construct() {
        add_action('wp_ajax_viraseo_schema_preview', [$this, 'ajax_preview']);
        add_action('wp_ajax_viraseo_schema_bulk', [$this, 'ajax_bulk']);
        add_action('wp_ajax_viraseo_schema_save_custom', [$this, 'ajax_save_custom']);
        add_action('wp_ajax_viraseo_schema_toggle', [$this, 'ajax_toggle']);
        add_action('wp_ajax_viraseo_schema_settings_save', [$this, 'ajax_settings_save']);
        add_action('wp_head', [$this, 'inject_schema'], 99);
    }

    /* ===================== DETECTION ===================== */

    /**
     * Detect applicable schema types for a post.
     */
    public function detect_schema_type(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) return [];

        $content  = $post->post_content ?? '';
        $type     = $post->post_type;

        // WooCommerce product
        if ($type === 'product' && class_exists('WooCommerce')) {
            return ['Product', 'BreadcrumbList'];
        }

        // FAQ detection
        if ($this->has_faq_content($content)) {
            return ['Article', 'FAQPage', 'BreadcrumbList'];
        }

        // HowTo detection
        if ($this->has_howto_content($content)) {
            return ['Article', 'HowTo', 'BreadcrumbList'];
        }

        // Service page
        if ($type === 'page' && $this->has_service_keywords($content)) {
            return ['Service', 'BreadcrumbList'];
        }

        // Default for posts
        if ($type === 'post') {
            return ['Article', 'BreadcrumbList'];
        }

        // Default for pages and other types
        return ['WebPage', 'BreadcrumbList'];
    }

    private function has_faq_content(string $content): bool {
        // Check for Persian FAQ keywords
        if (mb_strpos($content, "\xD8\xB3\xD9\x88\xD8\xA7\xD9\x84\xD8\xA7\xD8\xAA \xD9\x85\xD8\xAA\xD8\xAF\xD8\xA7\xD9\x88\xD9\x84") !== false) return true;
        if (stripos($content, 'FAQ') !== false) return true;
        // Multiple Q&A patterns (dt/dd pairs or structured questions)
        if (preg_match_all('/<(dt|h[23])[^>]*>.*?\?/iu', $content, $m) && count($m[0]) >= 2) return true;
        return false;
    }

    private function has_howto_content(string $content): bool {
        // Numbered steps / ordered list with instructional content
        if (preg_match('/<ol[^>]*>.*?<li/is', $content)) {
            // Check if there are multiple list items (steps)
            if (preg_match_all('/<li[^>]*>/i', $content, $m) && count($m[0]) >= 3) {
                // Look for instructional language
                $instructional = [
                    "\xD9\x85\xD8\xB1\xD8\xAD\xD9\x84\xD9\x87", // مرحله
                    "\xDA\xAF\xD8\xA7\xD9\x85",                   // گام
                    "\xD9\x82\xD8\xAF\xD9\x85",                   // قدم
                    'step',
                ];
                foreach ($instructional as $word) {
                    if (mb_stripos($content, $word) !== false) return true;
                }
            }
        }
        return false;
    }

    private function has_service_keywords(string $content): bool {
        $keywords = [
            "\xD8\xAE\xD8\xAF\xD9\x85\xD8\xA7\xD8\xAA",       // خدمات
            "\xD9\x85\xD8\xB4\xD8\xA7\xD9\x88\xD8\xB1\xD9\x87", // مشاوره
            "\xD8\xAE\xD8\xAF\xD9\x85\xD8\xA7\xD8\xAA \xD9\x85\xD8\xA7", // خدمات ما
            "\xD8\xB3\xD8\xB1\xD9\x88\xDB\x8C\xD8\xB3",         // سرویس
        ];
        foreach ($keywords as $kw) {
            if (mb_strpos($content, $kw) !== false) return true;
        }
        return false;
    }

    /* ===================== GENERATION ===================== */

    /**
     * Build full JSON-LD structures for a post.
     */
    public function generate_schema(int $post_id): array {
        $types = $this->detect_schema_type($post_id);
        if (empty($types)) return [];

        $post    = get_post($post_id);
        if (!$post) return [];

        $schemas = [];

        foreach ($types as $type) {
            switch ($type) {
                case 'Article':
                    $schemas[] = $this->build_article($post);
                    break;
                case 'Product':
                    $schemas[] = $this->build_product($post);
                    break;
                case 'FAQPage':
                    $schemas[] = $this->build_faq($post);
                    break;
                case 'HowTo':
                    $schemas[] = $this->build_howto($post);
                    break;
                case 'BreadcrumbList':
                    $schemas[] = $this->build_breadcrumb($post);
                    break;
                case 'Service':
                    $schemas[] = $this->build_service($post);
                    break;
                case 'WebPage':
                    $schemas[] = $this->build_webpage($post);
                    break;
            }
        }

        return array_filter($schemas);
    }

    private function build_article(\WP_Post $post): array {
        $image = get_the_post_thumbnail_url($post->ID, 'full');
        $site  = get_bloginfo('name');

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'Article',
            'headline'        => $post->post_title,
            'author'          => ['@type' => 'Organization', 'name' => $site],
            'datePublished'   => get_the_date('c', $post),
            'dateModified'    => get_the_modified_date('c', $post),
            'image'           => $image ?: null,
            'publisher'       => [
                '@type' => 'Organization',
                'name'  => $site,
                'logo'  => ['@type' => 'ImageObject', 'url' => get_site_icon_url()],
            ],
            'description'     => wp_trim_words(strip_tags($post->post_content), 30, '...'),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => get_permalink($post->ID)],
        ];
    }

    private function build_product(\WP_Post $post): array {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $post->post_title,
            'description' => wp_trim_words(strip_tags($post->post_content), 30, '...'),
            'image'       => get_the_post_thumbnail_url($post->ID, 'full') ?: null,
        ];

        if (function_exists('wc_get_product')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $schema['sku']  = $product->get_sku();
                $schema['brand'] = ['@type' => 'Brand', 'name' => get_bloginfo('name')];
                $schema['offers'] = [
                    '@type'         => 'Offer',
                    'price'         => $product->get_price(),
                    'priceCurrency' => get_woocommerce_currency(),
                    'availability'  => $product->is_in_stock()
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                ];
            }
        }

        return $schema;
    }

    private function build_faq(\WP_Post $post): array {
        $content = $post->post_content;
        $questions = [];

        // Extract H2/H3 + following paragraph pairs
        if (preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>\s*<p[^>]*>(.*?)<\/p>/isu', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $q = wp_strip_all_tags($m[1]);
                $a = wp_strip_all_tags($m[2]);
                if ($q && $a) {
                    $questions[] = [
                        '@type'          => 'Question',
                        'name'           => $q,
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
                    ];
                }
            }
        }

        // Also try dt/dd patterns
        if (preg_match_all('/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/isu', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $q = wp_strip_all_tags($m[1]);
                $a = wp_strip_all_tags($m[2]);
                if ($q && $a) {
                    $questions[] = [
                        '@type'          => 'Question',
                        'name'           => $q,
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
                    ];
                }
            }
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $questions,
        ];
    }

    private function build_howto(\WP_Post $post): array {
        $content = $post->post_content;
        $steps   = [];

        // Extract steps from ol/li patterns
        if (preg_match('/<ol[^>]*>(.*?)<\/ol>/is', $content, $ol_match)) {
            if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $ol_match[1], $li_matches)) {
                $position = 1;
                foreach ($li_matches[1] as $li_text) {
                    $text = wp_strip_all_tags($li_text);
                    if ($text) {
                        $steps[] = [
                            '@type'    => 'HowToStep',
                            'position' => $position,
                            'text'     => $text,
                        ];
                        $position++;
                    }
                }
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@type'    => 'HowTo',
            'name'     => $post->post_title,
            'step'     => $steps,
        ];
    }

    private function build_breadcrumb(\WP_Post $post): array {
        $items = [];
        $position = 1;

        // Home
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => get_bloginfo('name'),
            'item'     => home_url('/'),
        ];

        // Category hierarchy for posts
        $categories = get_the_category($post->ID);
        if ($categories) {
            // Sort by parent (deepest last)
            usort($categories, function($a, $b) { return $a->parent <=> $b->parent; });
            foreach ($categories as $cat) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'name'     => $cat->name,
                    'item'     => get_category_link($cat->term_id),
                ];
            }
        }

        // Current page
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position,
            'name'     => $post->post_title,
            'item'     => get_permalink($post->ID),
        ];

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    private function build_service(\WP_Post $post): array {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            'name'        => $post->post_title,
            'description' => wp_trim_words(strip_tags($post->post_content), 30, '...'),
            'provider'    => [
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
                'url'   => home_url('/'),
            ],
        ];
    }

    private function build_webpage(\WP_Post $post): array {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'WebPage',
            'name'        => $post->post_title,
            'description' => wp_trim_words(strip_tags($post->post_content), 30, '...'),
            'url'         => get_permalink($post->ID),
        ];
    }

    /* ===================== FRONTEND INJECTION ===================== */

    public function inject_schema(): void {
        if (!is_singular()) return;

        $settings = $this->get_settings();
        if (empty($settings['enabled'])) return;

        $post_id = get_the_ID();
        if (!$post_id) return;

        // Per-post disable
        if (get_post_meta($post_id, self::META_DISABLED, true)) return;

        // Check if another SEO plugin is already injecting schema
        if ($this->other_schema_active()) return;

        // Custom schema override
        $custom = get_post_meta($post_id, self::META_CUSTOM, true);
        if ($custom) {
            $decoded = json_decode($custom, true);
            if (is_array($decoded)) {
                echo "\n" . '<script type="application/ld+json">' . wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
                return;
            }
        }

        // Check excluded post types
        $post_type = get_post_type($post_id);
        if (!empty($settings['excluded_types']) && in_array($post_type, $settings['excluded_types'], true)) return;

        $schemas = $this->generate_schema($post_id);
        if (empty($schemas)) return;

        // Filter by allowed auto_types
        if (!empty($settings['auto_types'])) {
            $schemas = array_filter($schemas, function($s) use ($settings) {
                return isset($s['@type']) && in_array($s['@type'], $settings['auto_types'], true);
            });
        }

        foreach ($schemas as $schema) {
            if (!empty($schema)) {
                echo "\n" . '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
            }
        }
    }

    private function other_schema_active(): bool {
        // Yoast SEO
        if (defined('WPSEO_VERSION') && has_action('wpseo_json_ld')) return true;
        // RankMath
        if (class_exists('RankMath') && has_filter('rank_math/json_ld')) return true;
        // AIOSEO
        if (defined('AIOSEO_VERSION') || function_exists('aioseo')) return true;

        return false;
    }

    /* ===================== SETTINGS ===================== */

    public function get_settings(): array {
        return wp_parse_args(get_option(self::OPTION_KEY, []), [
            'enabled'        => true,
            'excluded_types' => [],
            'auto_types'     => ['Article', 'Product', 'FAQPage', 'HowTo', 'BreadcrumbList', 'Service', 'WebPage'],
        ]);
    }

    /* ===================== AJAX HANDLERS ===================== */

    public function ajax_preview(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error("\xD8\xAF\xD8\xB3\xD8\xAA\xD8\xB1\xD8\xB3\xDB\x8C \xD9\x86\xD8\xAF\xD8\xA7\xD8\xB1\xDB\x8C\xD8\xAF.");

        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id) wp_send_json_error("\xD8\xB4\xD9\x86\xD8\xA7\xD8\xB3\xD9\x87 \xD9\x86\xD8\xA7\xD9\x85\xD8\xB9\xD8\xAA\xD8\xA8\xD8\xB1.");

        $types   = $this->detect_schema_type($post_id);
        $schemas = $this->generate_schema($post_id);
        $custom  = get_post_meta($post_id, self::META_CUSTOM, true);

        wp_send_json_success([
            'types'   => $types,
            'schemas' => $schemas,
            'custom'  => $custom ?: null,
            'json_ld' => wp_json_encode($schemas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        ]);
    }

    public function ajax_bulk(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error("\xD8\xAF\xD8\xB3\xD8\xAA\xD8\xB1\xD8\xB3\xDB\x8C \xD9\x86\xD8\xAF\xD8\xA7\xD8\xB1\xDB\x8C\xD8\xAF.");

        $page      = max(1, absint($_POST['page'] ?? 1));
        $per_page  = 20;
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');

        $args = [
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ];

        if ($post_type) {
            $args['post_type'] = $post_type;
        } else {
            $args['post_type'] = ['post', 'page', 'product'];
        }

        $query = new \WP_Query($args);
        $rows  = [];

        foreach ($query->posts as $p) {
            $types    = $this->detect_schema_type($p->ID);
            $disabled = (bool) get_post_meta($p->ID, self::META_DISABLED, true);
            $custom   = get_post_meta($p->ID, self::META_CUSTOM, true);

            $rows[] = [
                'id'        => $p->ID,
                'title'     => $p->post_title,
                'post_type' => $p->post_type,
                'types'     => $types,
                'disabled'  => $disabled,
                'has_custom' => !empty($custom),
                'edit_url'  => get_edit_post_link($p->ID, 'raw'),
            ];
        }

        wp_send_json_success([
            'rows'  => $rows,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'page'  => $page,
        ]);
    }

    public function ajax_save_custom(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error("\xD8\xAF\xD8\xB3\xD8\xAA\xD8\xB1\xD8\xB3\xDB\x8C \xD9\x86\xD8\xAF\xD8\xA7\xD8\xB1\xDB\x8C\xD8\xAF.");

        $post_id = absint($_POST['post_id'] ?? 0);
        $json    = wp_unslash($_POST['custom_schema'] ?? '');

        if (!$post_id) wp_send_json_error("\xD8\xB4\xD9\x86\xD8\xA7\xD8\xB3\xD9\x87 \xD9\x86\xD8\xA7\xD9\x85\xD8\xB9\xD8\xAA\xD8\xA8\xD8\xB1.");

        // Validate JSON
        $decoded = json_decode($json, true);
        if ($json && json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('JSON \xD9\x86\xD8\xA7\xD9\x85\xD8\xB9\xD8\xAA\xD8\xA8\xD8\xB1 \xD8\xA7\xD8\xB3\xD8\xAA.');
        }

        if ($json && $decoded) {
            update_post_meta($post_id, self::META_CUSTOM, $json);
        } else {
            delete_post_meta($post_id, self::META_CUSTOM);
        }

        wp_send_json_success([
            'message' => "\xD8\xA7\xD8\xB3\xDA\xA9\xDB\x8C\xD9\x85\xD8\xA7\xDB\x8C \xD8\xB3\xD9\x81\xD8\xA7\xD8\xB1\xD8\xB4\xDB\x8C \xD8\xB0\xD8\xAE\xDB\x8C\xD8\xB1\xD9\x87 \xD8\xB4\xD8\xAF.",
        ]);
    }

    public function ajax_toggle(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error("\xD8\xAF\xD8\xB3\xD8\xAA\xD8\xB1\xD8\xB3\xDB\x8C \xD9\x86\xD8\xAF\xD8\xA7\xD8\xB1\xDB\x8C\xD8\xAF.");

        $post_id = absint($_POST['post_id'] ?? 0);
        $enabled = !empty($_POST['enabled']);

        if (!$post_id) wp_send_json_error("\xD8\xB4\xD9\x86\xD8\xA7\xD8\xB3\xD9\x87 \xD9\x86\xD8\xA7\xD9\x85\xD8\xB9\xD8\xAA\xD8\xA8\xD8\xB1.");

        if ($enabled) {
            delete_post_meta($post_id, self::META_DISABLED);
        } else {
            update_post_meta($post_id, self::META_DISABLED, '1');
        }

        wp_send_json_success([
            'message' => $enabled
                ? "\xD8\xA7\xD8\xB3\xDA\xA9\xDB\x8C\xD9\x85\xD8\xA7 \xD9\x81\xD8\xB9\xD8\xA7\xD9\x84 \xD8\xB4\xD8\xAF."
                : "\xD8\xA7\xD8\xB3\xDA\xA9\xDB\x8C\xD9\x85\xD8\xA7 \xD8\xBA\xDB\x8C\xD8\xB1\xD9\x81\xD8\xB9\xD8\xA7\xD9\x84 \xD8\xB4\xD8\xAF.",
            'disabled' => !$enabled,
        ]);
    }

    public function ajax_settings_save(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error("\xD8\xAF\xD8\xB3\xD8\xAA\xD8\xB1\xD8\xB3\xDB\x8C \xD9\x86\xD8\xAF\xD8\xA7\xD8\xB1\xDB\x8C\xD8\xAF.");

        $enabled        = !empty($_POST['enabled']);
        $excluded_types = array_filter(array_map('sanitize_text_field', (array)($_POST['excluded_types'] ?? [])));
        $auto_types     = array_filter(array_map('sanitize_text_field', (array)($_POST['auto_types'] ?? [])));

        update_option(self::OPTION_KEY, [
            'enabled'        => $enabled,
            'excluded_types' => $excluded_types,
            'auto_types'     => $auto_types,
        ]);

        wp_send_json_success([
            'message' => "\xD8\xAA\xD9\x86\xD8\xB8\xDB\x8C\xD9\x85\xD8\xA7\xD8\xAA \xD8\xA7\xD8\xB3\xDA\xA9\xDB\x8C\xD9\x85\xD8\xA7 \xD8\xB0\xD8\xAE\xDB\x8C\xD8\xB1\xD9\x87 \xD8\xB4\xD8\xAF.",
        ]);
    }
}
