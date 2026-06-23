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

        $r = wp_remote_get(self::ENDPOINT . '/models', [
            'timeout' => 25,
            'headers' => ['Authorization' => 'Bearer ' . $key],
        ]);
        if (is_wp_error($r)) return ['error' => 'خطا در اتصال به OpenRouter: ' . $r->get_error_message()];
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

    /** Run a chat completion. Returns ['text'=>..., 'cost'=>..., 'tokens'=>...] or ['error'=>...]. */
    public static function chat(string $system, string $user, float $temperature = 0.4): array {
        $key = Dashboard::get('openrouter_key');
        if (!$key) return ['error' => 'کلید OpenRouter وارد نشده.'];
        $model = self::model();

        $r = wp_remote_post(self::ENDPOINT . '/chat/completions', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => home_url(),
                'X-Title'       => 'ViraSEO',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => $temperature,
            ]),
        ]);
        if (is_wp_error($r)) return ['error' => 'خطا در اتصال به AI: ' . $r->get_error_message()];
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
