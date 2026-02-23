<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Throwable;

class UiDate
{
    private const AD_MONTHS_NE = [
        1 => 'जनवरी',
        2 => 'फेब्रुअरी',
        3 => 'मार्च',
        4 => 'अप्रिल',
        5 => 'मे',
        6 => 'जुन',
        7 => 'जुलाई',
        8 => 'अगस्ट',
        9 => 'सेप्टेम्बर',
        10 => 'अक्टोबर',
        11 => 'नोभेम्बर',
        12 => 'डिसेम्बर',
    ];

    private const AD_WEEKDAYS_NE = [
        0 => 'आइत',
        1 => 'सोम',
        2 => 'मंगल',
        3 => 'बुध',
        4 => 'बिही',
        5 => 'शुक्र',
        6 => 'शनि',
    ];

    /**
     * @param CarbonInterface|DateTimeInterface|string|null $date
     */
    public static function formatAd(CarbonInterface|DateTimeInterface|string|null $date, bool $withTime = false, ?string $locale = null): string
    {
        $carbon = self::normalize($date);
        if ($carbon === null) {
            return '';
        }

        $localeCode = self::locale($locale);
        if ($localeCode === 'ne') {
            $weekday = self::AD_WEEKDAYS_NE[$carbon->dayOfWeek] ?? '';
            $month = self::AD_MONTHS_NE[$carbon->month] ?? $carbon->format('M');
            $day = NepaliDate::toNepaliDigits((string) $carbon->day);
            $year = NepaliDate::toNepaliDigits((string) $carbon->year);
            $base = trim($weekday . ', ' . $month . ' ' . $day . ', ' . $year);
            $suffix = 'ई.सं.';

            if (!$withTime) {
                return $base . ' ' . $suffix;
            }

            $time = self::nepaliTime($carbon);
            return trim($base . ' ' . $time . ' ' . $suffix);
        }

        $base = $carbon->format($withTime ? 'D, M j, Y h:i A' : 'D, M j, Y');
        return $base . ' AD';
    }

    /**
     * @param CarbonInterface|DateTimeInterface|string|null $date
     */
    public static function formatBs(CarbonInterface|DateTimeInterface|string|null $date, ?string $locale = null, bool $includeWeekday = false): string
    {
        $carbon = self::normalize($date);
        if ($carbon === null) {
            return '';
        }

        try {
            return NepaliDate::format($carbon, self::locale($locale), $includeWeekday);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param CarbonInterface|DateTimeInterface|string|null $date
     */
    public static function dual(CarbonInterface|DateTimeInterface|string|null $date, bool $withTime = false, ?string $locale = null): string
    {
        $carbon = self::normalize($date);
        if ($carbon === null) {
            return '';
        }

        $localeCode = self::locale($locale);
        $ad = self::formatAd($carbon, $withTime, $localeCode);
        $bs = self::formatBs($carbon, $localeCode, !$withTime);
        if ($bs === '') {
            return $ad;
        }

        return $localeCode === 'ne'
            ? ($bs . ' | ' . $ad)
            : ($ad . ' | ' . $bs);
    }

    /**
     * @param CarbonInterface|DateTimeInterface|string|null $start
     * @param CarbonInterface|DateTimeInterface|string|null $end
     */
    public static function dualRange(CarbonInterface|DateTimeInterface|string|null $start, CarbonInterface|DateTimeInterface|string|null $end, ?string $locale = null): string
    {
        $left = self::dual($start, false, $locale);
        $right = self::dual($end, false, $locale);
        if ($left === '' && $right === '') {
            return '';
        }
        if ($left === '') {
            return $right;
        }
        if ($right === '') {
            return $left;
        }

        return $left . ' — ' . $right;
    }

    private static function locale(?string $locale = null): string
    {
        $normalized = strtolower(trim((string) ($locale ?? app()->getLocale())));
        return $normalized === 'ne' ? 'ne' : 'en';
    }

    /**
     * @param CarbonInterface|DateTimeInterface|string|null $date
     */
    private static function normalize(CarbonInterface|DateTimeInterface|string|null $date): ?CarbonImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof CarbonInterface || $date instanceof DateTimeInterface) {
            return CarbonImmutable::instance($date)->setTimezone('Asia/Kathmandu');
        }

        $raw = trim((string) $date);
        if ($raw === '') {
            return null;
        }

        return CarbonImmutable::parse($raw, 'Asia/Kathmandu')->setTimezone('Asia/Kathmandu');
    }

    private static function nepaliTime(CarbonImmutable $carbon): string
    {
        $hour = NepaliDate::toNepaliDigits($carbon->format('h'));
        $minute = NepaliDate::toNepaliDigits($carbon->format('i'));
        $meridiem = $carbon->hour < 12 ? 'बिहान' : 'साँझ';

        return $hour . ':' . $minute . ' ' . $meridiem;
    }
}
