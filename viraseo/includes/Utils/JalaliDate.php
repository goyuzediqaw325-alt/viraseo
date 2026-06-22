<?php
namespace ViraSEO\Utils;

class JalaliDate {

    private static array $months = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    /**
     * Convert Gregorian date to Jalali.
     */
    public static function gregorian_to_jalali(int $gy, int $gm, int $gd): array {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intval(($gy2 + 3) / 4) - intval(($gy2 + 99) / 100)
            + intval(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intval($days / 12053));
        $days = $days % 12053;
        $jy += 4 * intval($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intval(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intval($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intval(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [
            'year' => $jy,
            'month' => $jm,
            'day' => $jd,
            'date' => sprintf('%04d/%02d/%02d', $jy, $jm, $jd),
        ];
    }

    /**
     * Convert Jalali date to Gregorian.
     */
    public static function jalali_to_gregorian(int $jy, int $jm, int $jd): array {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intval($jy / 33) * 8) + intval((($jy % 33) + 3) / 4)
            + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intval($days / 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intval(--$days / 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intval($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intval(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $sal_a = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28,
            31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 0;
        for ($i = 1; $i <= 12; $i++) {
            if ($gd <= $sal_a[$i]) {
                $gm = $i;
                break;
            }
            $gd -= $sal_a[$i];
        }

        return [
            'year' => $gy,
            'month' => $gm,
            'day' => $gd,
            'date' => sprintf('%04d-%02d-%02d', $gy, $gm, $gd),
        ];
    }

    /**
     * Get current Jalali date.
     */
    public static function now(): array {
        $gy = (int) date('Y');
        $gm = (int) date('m');
        $gd = (int) date('d');
        return self::gregorian_to_jalali($gy, $gm, $gd);
    }

    /**
     * Get current Jalali date and time as string.
     */
    public static function now_datetime(): string {
        $jalali = self::now();
        return $jalali['date'] . ' ' . date('H:i:s');
    }

    /**
     * Convert Western digits to Persian digits.
     */
    public static function to_persian_digits(string|int|float $input): string {
        $input = (string) $input;
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($western, $persian, $input);
    }

    /**
     * Convert Persian digits to Western digits.
     */
    public static function to_western_digits(string $input): string {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($persian, $western, $input);
    }

    /**
     * Convert a Jalali date string (YYYY/MM/DD) to Gregorian (YYYY-MM-DD).
     */
    public static function jalali_string_to_gregorian(string $jalali_date): ?string {
        $jalali_date = self::to_western_digits(trim($jalali_date));
        $parts = preg_split('/[\/\-]/', $jalali_date);
        if (count($parts) !== 3) {
            return null;
        }

        $jy = (int) $parts[0];
        $jm = (int) $parts[1];
        $jd = (int) $parts[2];

        if ($jy < 1 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
            return null;
        }

        $result = self::jalali_to_gregorian($jy, $jm, $jd);
        return $result['date'];
    }

    /**
     * Format a datetime string into Persian format.
     * Supports: date, datetime, long, relative
     */
    public static function format(string $datetime, string $format = 'date'): string {
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return $datetime;
        }

        $gy = (int) date('Y', $timestamp);
        $gm = (int) date('m', $timestamp);
        $gd = (int) date('d', $timestamp);
        $jalali = self::gregorian_to_jalali($gy, $gm, $gd);

        switch ($format) {
            case 'date':
                return self::to_persian_digits($jalali['date']);

            case 'datetime':
                $time = date('H:i', $timestamp);
                return self::to_persian_digits($jalali['date'] . ' ' . $time);

            case 'long':
                $month_name = self::$months[$jalali['month']] ?? '';
                $day = self::to_persian_digits($jalali['day']);
                $year = self::to_persian_digits($jalali['year']);
                return "{$day} {$month_name} {$year}";

            case 'relative':
                return self::relative_time($timestamp);

            default:
                return self::to_persian_digits($jalali['date']);
        }
    }

    /**
     * Get relative time in Persian (e.g., "۲ ساعت پیش").
     */
    public static function relative_time(int $timestamp): string {
        $now = time();
        $diff = $now - $timestamp;

        if ($diff < 0) {
            $diff = abs($diff);
            $suffix = 'بعد';
        } else {
            $suffix = 'پیش';
        }

        if ($diff < 60) {
            return 'لحظاتی ' . $suffix;
        }

        if ($diff < 3600) {
            $minutes = intval($diff / 60);
            return self::to_persian_digits($minutes) . ' دقیقه ' . $suffix;
        }

        if ($diff < 86400) {
            $hours = intval($diff / 3600);
            return self::to_persian_digits($hours) . ' ساعت ' . $suffix;
        }

        if ($diff < 604800) {
            $days = intval($diff / 86400);
            return self::to_persian_digits($days) . ' روز ' . $suffix;
        }

        if ($diff < 2592000) {
            $weeks = intval($diff / 604800);
            return self::to_persian_digits($weeks) . ' هفته ' . $suffix;
        }

        if ($diff < 31536000) {
            $months = intval($diff / 2592000);
            return self::to_persian_digits($months) . ' ماه ' . $suffix;
        }

        $years = intval($diff / 31536000);
        return self::to_persian_digits($years) . ' سال ' . $suffix;
    }
}
