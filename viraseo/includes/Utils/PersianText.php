<?php
namespace ViraSEO\Utils;

class PersianText {

    private const ZWNJ = "\xE2\x80\x8C";

    private static array $stop_words = [
        'و', 'در', 'به', 'از', 'که', 'این', 'را', 'با', 'است',
        'برای', 'آن', 'یک', 'خود', 'تا', 'کرد', 'بر', 'هم',
        'نیز', 'گفت', 'می', 'شد', 'ها', 'های', 'اما', 'یا',
        'شده', 'باید', 'هر', 'آنها', 'بود', 'شود', 'وی', 'دارد',
        'ما', 'من', 'شما', 'او', 'چه', 'اگر', 'همه', 'بین',
        'پس', 'زیر', 'چون', 'پیش', 'روی', 'نه', 'ولی', 'کنند',
        'بعد', 'درباره', 'همین', 'کند',
    ];

    private static array $arabic_to_persian = [
        'ك' => 'ک',
        'ي' => 'ی',
        'ە' => 'ه',
    ];

    /**
     * Normalize a Persian string: Arabic→Persian, remove diacritics, collapse ZWNJ, trim.
     */
    public static function normalize(string $text): string {
        // Arabic to Persian character replacement
        $text = str_replace(
            array_keys(self::$arabic_to_persian),
            array_values(self::$arabic_to_persian),
            $text
        );

        // Remove Arabic diacritics (tashkil): fathah, dammah, kasrah, sukun, shadda, tanwin
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);

        // Collapse multiple ZWNJs into one
        $zwnj = self::ZWNJ;
        $text = preg_replace('/(' . preg_quote($zwnj, '/') . '){2,}/u', $zwnj, $text);

        // Remove ZWNJ at start/end of words
        $text = preg_replace('/^\s*' . preg_quote($zwnj, '/') . '/u', '', $text);
        $text = preg_replace('/' . preg_quote($zwnj, '/') . '\s*$/u', '', $text);

        // Collapse multiple spaces
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Normalize a keyword: normalize + replace ZWNJ with space + lowercase.
     */
    public static function normalize_keyword(string $keyword): string {
        $keyword = self::normalize($keyword);
        $keyword = str_replace(self::ZWNJ, ' ', $keyword);
        $keyword = mb_strtolower($keyword, 'UTF-8');
        $keyword = preg_replace('/\s+/u', ' ', $keyword);
        return trim($keyword);
    }

    /**
     * Tokenize text: split on whitespace, filter to Persian chars only.
     */
    public static function tokenize(string $text): array {
        $text = self::normalize($text);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        $tokens = [];
        foreach ($words as $word) {
            // Keep only words that contain Persian/Arabic Unicode characters
            if (preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $word)) {
                // Strip non-Persian characters from edges
                $clean = preg_replace('/[^\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}\x{200C}]/u', '', $word);
                if (!empty($clean)) {
                    $tokens[] = $clean;
                }
            }
        }

        return $tokens;
    }

    /**
     * Remove stop words from an array of tokens.
     */
    public static function remove_stop_words(array $tokens): array {
        return array_values(array_filter($tokens, function ($token) {
            return !in_array($token, self::$stop_words, true);
        }));
    }

    /**
     * Extract keywords from text: tokenize, remove stops, count frequency, return top N.
     */
    public static function extract_keywords(string $text, int $limit = 20): array {
        $tokens = self::tokenize($text);
        $tokens = self::remove_stop_words($tokens);

        $frequency = [];
        foreach ($tokens as $token) {
            if (mb_strlen($token, 'UTF-8') < 2) {
                continue;
            }
            if (!isset($frequency[$token])) {
                $frequency[$token] = 0;
            }
            $frequency[$token]++;
        }

        arsort($frequency);

        return array_slice($frequency, 0, $limit, true);
    }

    /**
     * Format a number with Persian digits and thousand separators.
     */
    public static function format_number(int|float $number): string {
        $formatted = number_format($number);
        return JalaliDate::to_persian_digits($formatted);
    }

    /**
     * Check if a string is predominantly Persian (>50% Persian characters).
     */
    public static function is_persian(string $text): bool {
        $text = preg_replace('/\s+/u', '', $text);
        if (empty($text)) {
            return false;
        }

        $total_chars = mb_strlen($text, 'UTF-8');
        preg_match_all('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text, $matches);
        $persian_chars = count($matches[0]);

        return ($persian_chars / $total_chars) > 0.5;
    }
}
