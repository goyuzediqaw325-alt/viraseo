<?php
namespace ViraSEO\Api;
defined('ABSPATH') || exit;

use ViraSEO\Admin\Dashboard;

/**
 * OpenRouter AI client — optional, user-enabled.
 * Lets users pick any OpenRouter model and run advanced SEO analyses with cost transparency.
 */
class AiClient {
    const ENDPOINT = 'https://openrouter.ai/api/v1';
    private static bool $proxy_active = false;

    /** Base URL — routes through a Cloudflare Worker proxy if configured (for Iran hosts). */
    private static function base(): string {
        $proxy = Dashboard::get('ai_proxy_url');
        return $proxy ? rtrim($proxy, '/') . '/v1' : self::ENDPOINT;
    }

    /** Hooked to http_api_curl — routes AI requests through a custom proxy (xray/SOCKS/HTTP)
     *  when configured. Only applies while an AI request is in flight. */
    public static function apply_curl_proxy($handle): void {
        if (!self::$proxy_active) return;
        // Always enforce adequate timeout for AI calls (host/proxy defaults may be 30s)
        curl_setopt($handle, CURLOPT_TIMEOUT, 150);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
        $px = Dashboard::get('ai_curl_proxy');
        if (!$px) return;
        curl_setopt($handle, CURLOPT_PROXY, $px);
        // socks5h:// / socks5:// / http:// are auto-detected from the scheme by cURL
    }

    public static function is_enabled(): bool {
        return Dashboard::get('ai_enabled') && Dashboard::get('openrouter_key');
    }

    public static function model(): string {
        return Dashboard::get('ai_model') ?: 'openai/gpt-4o-mini';
    }

    /** Fetch available models + pricing (cached 12h). */
    public static function models(bool $force = false): array {
        $key = Dashboard::get('openrouter_key');
        if (!$key) return ['error' => 'کلید OpenRouter وارد نشده.'];
        $cache = get_transient('viraseo_or_models');
        if ($cache && !$force) return ['models' => $cache];

        self::$proxy_active = true;
        $r = wp_remote_get(self::base() . '/models', [
            'timeout' => 25,
            'headers' => ['Authorization' => 'Bearer ' . $key, 'X-Site-Url' => home_url()],
        ]);
        self::$proxy_active = false;
        if (is_wp_error($r)) return ['error' => 'خطا در اتصال به ' . self::base() . ' — ' . $r->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if (empty($body['data'])) return ['error' => 'لیست مدل‌ها دریافت نشد. کلید را بررسی کنید.'];

        $models = [];
        foreach ($body['data'] as $m) {
            $pin = (float)($m['pricing']['prompt'] ?? 0);
            $pout = (float)($m['pricing']['completion'] ?? 0);
            $models[] = [
                'id'   => $m['id'] ?? '',
                'name' => $m['name'] ?? ($m['id'] ?? ''),
                // Cost per 1M tokens (USD)
                'in'   => round($pin * 1000000, 3),
                'out'  => round($pout * 1000000, 3),
                'free' => ($pin == 0 && $pout == 0),
            ];
        }
        // Sort: cheapest first
        usort($models, fn($a, $b) => ($a['in'] + $a['out']) <=> ($b['in'] + $b['out']));
        set_transient('viraseo_or_models', $models, 12 * HOUR_IN_SECONDS);
        return ['models' => $models];
    }

    /** Quick connectivity test (used by Diagnostics). Returns status + latency + which URL. */
    public static function test(): array {
        $key = Dashboard::get('openrouter_key');
        if (!Dashboard::get('ai_enabled')) return ['ok'=>false, 'msg'=>'هوش مصنوعی در تنظیمات فعال نیست.'];
        if (!$key) return ['ok'=>false, 'msg'=>'کلید OpenRouter وارد نشده.'];
        $base = self::base();
        $proxy = Dashboard::get('ai_proxy_url') ? 'پروکسی Cloudflare' : 'اتصال مستقیم به OpenRouter';
        $t0 = microtime(true);
        self::$proxy_active = true;
        $r = wp_remote_get($base . '/models', [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $key, 'X-Site-Url' => home_url()],
        ]);
        self::$proxy_active = false;
        $ms = (int) round((microtime(true) - $t0) * 1000);
        if (is_wp_error($r)) {
            return ['ok'=>false, 'msg'=>'❌ اتصال ناموفق ('.$proxy.'): '.$r->get_error_message().' — '.$base, 'ms'=>$ms];
        }
        $code = (int) wp_remote_retrieve_response_code($r);
        if ($code === 401) return ['ok'=>false, 'msg'=>'❌ کلید OpenRouter نامعتبر است (۴۰۱).', 'ms'=>$ms];
        if ($code < 200 || $code >= 300) return ['ok'=>false, 'msg'=>'❌ پاسخ HTTP '.$code.' از '.$base, 'ms'=>$ms];
        $body = json_decode(wp_remote_retrieve_body($r), true);
        $n = is_array($body['data'] ?? null) ? count($body['data']) : 0;
        return ['ok'=>true, 'msg'=>'✅ اتصال موفق ('.$proxy.') — '.$n.' مدل در '.$ms.' میلی‌ثانیه.', 'ms'=>$ms];
    }

    /** Run a chat completion. Returns ['text'=>..., 'cost'=>..., 'tokens'=>...] or ['error'=>...].
     * For long operations (rewrites), automatically splits into two calls if needed. */
    public static function chat(string $system, string $user, float $temperature = 0.4, int $max_tokens = 2000): array {
        $key = Dashboard::get('openrouter_key');
        if (!$key) return ['error' => 'کلید OpenRouter وارد نشده.'];
        $model = self::model();

        // Ensure PHP has enough execution time for long AI responses
        $orig_time = (int) ini_get('max_execution_time');
        if ($orig_time > 0 && $orig_time < 300) {
            @set_time_limit(300);
        }

        // Cap max_tokens to 3000 to keep response time within Cloudflare Worker 30s limit.
        // 3000 tokens ≈ 2000+ words Persian — sufficient for most page rewrites.
        $effective_max = min($max_tokens, 3000);

        // Register the curl timeout hook if not already registered
        if (!has_action('http_api_curl', [self::class, 'apply_curl_proxy'])) {
            add_action('http_api_curl', [self::class, 'apply_curl_proxy'], 10, 1);
        }

        self::$proxy_active = true;
        $r = wp_remote_post(self::base() . '/chat/completions', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => home_url(),
                'X-Site-Url'    => home_url(),
                'X-Title'       => 'ViraSEO',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => $temperature,
                'max_tokens' => $effective_max,
            ]),
        ]);
        self::$proxy_active = false;
        if (is_wp_error($r)) return ['error' => 'خطا در اتصال به ' . self::base() . ' — ' . $r->get_error_message() . ' (اگر هاست ایران است، پروکسی Cloudflare را در تنظیمات تعریف کنید)'];
        $code = wp_remote_retrieve_response_code($r);
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if ($code < 200 || $code >= 300) {
            return ['error' => 'AI خطا داد (HTTP ' . $code . '): ' . ($body['error']['message'] ?? '')];
        }
        $text = $body['choices'][0]['message']['content'] ?? '';
        if ($text === '') return ['error' => 'پاسخ خالی از AI.'];

        // Approximate cost from usage + cached pricing
        $usage = $body['usage'] ?? [];
        $cost = self::estimate_cost($model, (int)($usage['prompt_tokens'] ?? 0), (int)($usage['completion_tokens'] ?? 0));
        return ['text' => $text, 'cost' => $cost, 'tokens' => (int)($usage['total_tokens'] ?? 0)];
    }

    /**
     * Clean AI HTML output: strip code fences, leading/trailing meta-commentary,
     * and any non-HTML text the model may have included outside the content.
     */
    public static function clean_html(string $raw): string {
        $text = $raw;
        // Remove markdown code fences
        $text = preg_replace('/^```(?:html|HTML)?\s*\n?/m', '', $text);
        $text = preg_replace('/\n?```\s*$/m', '', $text);
        $text = trim($text);
        // Strip leading plain-text lines before the first HTML element
        if (preg_match('/^(.+?)(<(?:h[1-6]|p|div|ul|ol|table|section|article|blockquote|details|summary|figure|aside)[>\s\/])/uis', $text, $m)) {
            $lead = trim($m[1]);
            // Only strip if the lead doesn't contain HTML tags itself (pure commentary)
            if (!preg_match('/<[a-z]/i', $lead)) {
                $text = substr($text, strlen($m[1]));
            }
        }
        // Strip trailing plain-text after the last closing HTML tag
        if (preg_match('/^(.*<\/(?:p|div|ul|ol|table|section|article|blockquote|h[1-6]|details|figure|aside)>)\s*[^<]+$/uis', $text, $m2)) {
            $text = $m2[1];
        }
        return trim($text);
    }

    /** Approximate USD cost for a request. */
    public static function estimate_cost(string $model, int $in_tokens, int $out_tokens): float {
        $cache = get_transient('viraseo_or_models') ?: [];
        foreach ($cache as $m) {
            if ($m['id'] === $model) {
                return round(($in_tokens * $m['in'] + $out_tokens * $m['out']) / 1000000, 5);
            }
        }
        return 0.0;
    }
}
