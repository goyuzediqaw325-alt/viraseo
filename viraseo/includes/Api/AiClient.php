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

    /** Hooked to http_api_curl — enforces timeout + applies cURL proxy for AI requests.
     *  Only applies while an AI request is in flight ($proxy_active=true). */
    public static function apply_curl_proxy($handle): void {
        if (!self::$proxy_active) return;

        // Force cURL timeout to 180s — overrides any WP/host default of 30s.
        // This is the REAL fix for "Operation timed out after 30001 milliseconds"
        // because wp_remote_post 'timeout' only sets CURLOPT_TIMEOUT if WP uses cURL transport,
        // but some hosts/plugins override it. Setting it here in the hook is authoritative.
        curl_setopt($handle, CURLOPT_TIMEOUT, 180);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);

        // Disable SSL verify for proxy connections (common for Iran proxies)
        $px = Dashboard::get('ai_curl_proxy');
        if (!$px) return;
        curl_setopt($handle, CURLOPT_PROXY, $px);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);

        // If proxy URL contains @ (auth), cURL handles it automatically from URL format
        // user:pass@host:port — but let's also set PROXYAUTH explicitly
        if (strpos($px, '@') !== false) {
            curl_setopt($handle, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
        }
    }

    public static function is_enabled(): bool {
        return Dashboard::get('ai_enabled') && Dashboard::get('openrouter_key');
    }

    /** Test the cURL proxy connectivity (not the Worker — the raw proxy). */
    public static function test_proxy(): array {
        $px = Dashboard::get('ai_curl_proxy');
        if (!$px) return ['ok' => false, 'msg' => 'پروکسی cURL تنظیم نشده.'];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://openrouter.ai/api/v1/models',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_PROXY => $px,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . Dashboard::get('openrouter_key')],
        ]);
        if (strpos($px, '@') !== false) {
            curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
        }
        $t0 = microtime(true);
        $body = curl_exec($ch);
        $ms = (int)round((microtime(true) - $t0) * 1000);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) return ['ok' => false, 'msg' => "❌ خطای پروکسی: {$err}"];
        if ($code === 0) return ['ok' => false, 'msg' => '❌ اتصال برقرار نشد. پروکسی در دسترس نیست یا پورت اشتباه است.'];
        if ($code === 407) return ['ok' => false, 'msg' => '❌ احراز هویت پروکسی ناموفق (407). نام‌کاربری/رمز را بررسی کنید.'];
        if ($code >= 400) return ['ok' => false, 'msg' => "❌ پروکسی جواب داد ولی OpenRouter خطا برگرداند (HTTP {$code})."];

        $data = json_decode($body, true);
        $n = count($data['data'] ?? []);
        return ['ok' => true, 'msg' => "✅ پروکسی سالم — اتصال به OpenRouter در {$ms}ms · {$n} مدل یافت شد."];
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
     * Uses streaming (chunked read) to avoid timeout even for long responses.
     * Each received chunk resets the idle timer, so the total time can be unlimited. */
    public static function chat(string $system, string $user, float $temperature = 0.4, int $max_tokens = 2000): array {
        $key = Dashboard::get('openrouter_key');
        if (!$key) return ['error' => 'کلید OpenRouter وارد نشده.'];
        $model = self::model();

        // Ensure PHP won't die during long AI responses
        $orig = (int) ini_get('max_execution_time');
        if ($orig > 0 && $orig < 300) @set_time_limit(300);

        // When going through CF Worker, cap tokens to fit in Worker's 30s CPU time
        $via_worker = !empty(Dashboard::get('ai_proxy_url'));
        // User preference: manual mode uses fixed value, auto mode uses the caller's $max_tokens
        $token_mode = Dashboard::get('ai_token_mode') ?: 'auto';
        $user_max = (int)(Dashboard::get('ai_max_tokens') ?: 4000);
        if ($token_mode === 'manual') {
            $effective_max = $user_max;
        } else {
            $effective_max = $via_worker ? min($max_tokens, 3000) : $max_tokens;
        }

        $url = self::base() . '/chat/completions';
        $payload = wp_json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => $temperature,
            'max_tokens' => $effective_max,
            'stream' => true, // Stream mode — avoids idle timeout
        ]);

        $headers = [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'HTTP-Referer: ' . home_url(),
            'X-Site-Url: ' . home_url(),
            'X-Title: ViraSEO',
        ];

        // Accumulate streamed chunks
        $buffer = '';
        $full_text = '';
        $usage = [];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 180,         // absolute max (safety net)
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_LOW_SPEED_LIMIT => 1,   // at least 1 byte/sec...
            CURLOPT_LOW_SPEED_TIME => 60,   // ...for 60s before timing out (idle timeout)
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$buffer, &$full_text, &$usage) {
                $buffer .= $chunk;
                // Process SSE lines
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $line = trim($line);
                    if ($line === '' || $line === 'data: [DONE]') continue;
                    if (strpos($line, 'data: ') === 0) {
                        $json = json_decode(substr($line, 6), true);
                        if ($json) {
                            $delta = $json['choices'][0]['delta']['content'] ?? '';
                            $full_text .= $delta;
                            if (!empty($json['usage'])) $usage = $json['usage'];
                        }
                    }
                }
                return strlen($chunk);
            },
        ]);

        // Apply cURL proxy if configured
        $px = Dashboard::get('ai_curl_proxy');
        if ($px) {
            curl_setopt($ch, CURLOPT_PROXY, $px);
            if (strpos($px, '@') !== false) {
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            }
        }

        self::$proxy_active = true;
        curl_exec($ch);
        self::$proxy_active = false;

        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) return ['error' => 'خطا در اتصال به ' . self::base() . ' — cURL: ' . $err . ' (اگر هاست ایران است، پروکسی را تنظیم کنید)'];
        if ($code >= 400) {
            // Try to parse error from accumulated text
            $errBody = json_decode($full_text ?: $buffer, true);
            return ['error' => 'AI خطا داد (HTTP ' . $code . '): ' . ($errBody['error']['message'] ?? $full_text)];
        }
        if ($full_text === '') return ['error' => 'پاسخ خالی از AI. (ممکن است مدل به مشکل خورده باشد)'];

        $cost = self::estimate_cost($model, (int)($usage['prompt_tokens'] ?? 0), (int)($usage['completion_tokens'] ?? 0));
        return ['text' => $full_text, 'cost' => $cost, 'tokens' => (int)($usage['total_tokens'] ?? 0)];
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
