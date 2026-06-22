<?php
namespace ViraSEO\Utils;
defined('ABSPATH') || exit;

class PersianText {
    private const ZWNJ = "\xE2\x80\x8C";
    private static array $stops = ['و','در','به','از','که','این','را','با','است','آن','یک','برای','تا','بر','هم','نیز','اما','یا','هر','شد','بود','خود','ها','های','می','شود','کرد','شده','دارد','باید','همه','شما','ما','من','او','چه','اگر','پس','بین','دیگر','فقط','هیچ'];

    public static function normalize(string $t): string {
        $t = str_replace(['ك','ي','ە'], ['ک','ی','ه'], $t);
        $t = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $t);
        $t = preg_replace('/' . preg_quote(self::ZWNJ, '/') . '{2,}/u', self::ZWNJ, $t);
        return trim(preg_replace('/\s+/u', ' ', $t));
    }

    public static function tokenize(string $t): array {
        $t = self::normalize($t);
        $words = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($words, fn($w) => (bool)preg_match('/[\x{0600}-\x{06FF}]/u', $w));
    }

    public static function word_count(string $t): int {
        $t = self::normalize($t);
        $words = preg_split('/\s+/u', trim($t), -1, PREG_SPLIT_NO_EMPTY);
        return count($words);
    }

    public static function extract_keywords(string $t, int $limit = 20): array {        $tokens = array_filter(self::tokenize($t), fn($w) => !in_array($w, self::$stops, true) && mb_strlen($w) > 2);
        $freq = array_count_values(array_map('mb_strtolower', $tokens));
        arsort($freq);
        return array_slice($freq, 0, $limit, true);
    }

    public static function format_number(int|float $n): string {
        return JalaliDate::to_fa(number_format($n, 0, '.', ','));
    }
}
