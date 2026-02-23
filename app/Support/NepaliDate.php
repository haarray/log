<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use OutOfRangeException;

class NepaliDate
{
    private const REFERENCE_AD = '1943-04-14'; // 2000-01-01 BS
    private const REFERENCE_BS_YEAR = 2000;
    private const REFERENCE_BS_MONTH = 1;
    private const REFERENCE_BS_DAY = 1;

    /**
     * Bikram Sambat month lengths.
     *
     * @var array<int, array<int, int>>
     */
    private const BS_MONTH_DAYS = [
        2000 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2001 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2002 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2003 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2004 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2005 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2006 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2007 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2008 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2009 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2010 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2011 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2012 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2013 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2014 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2015 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2016 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2017 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2018 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2019 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2020 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2021 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2022 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2023 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2024 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2025 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2026 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2027 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2028 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2029 => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
        2030 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2031 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2032 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2033 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2034 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2035 => [30, 32, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2036 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2037 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2038 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2039 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2040 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2041 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2042 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2043 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2044 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2045 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2046 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2047 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2048 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2049 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2050 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2051 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2052 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2053 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2054 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2055 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2056 => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
        2057 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2058 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2059 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2060 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2061 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2062 => [30, 32, 31, 32, 31, 31, 29, 30, 29, 30, 29, 31],
        2063 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2064 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2065 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2066 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2067 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2068 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2069 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2070 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2071 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2072 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2073 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2074 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2075 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2076 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2077 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2078 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2079 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2080 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2081 => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2082 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2083 => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
        2084 => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
        2085 => [31, 32, 31, 32, 30, 31, 30, 30, 29, 30, 30, 30],
        2086 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2087 => [31, 31, 32, 31, 31, 31, 30, 30, 29, 30, 30, 30],
        2088 => [30, 31, 32, 32, 30, 31, 30, 30, 29, 30, 30, 30],
        2089 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2090 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2091 => [31, 31, 32, 31, 31, 31, 30, 30, 29, 30, 30, 30],
        2092 => [31, 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30],
    ];

    private const MONTHS_EN = [
        1 => 'Baisakh',
        2 => 'Jestha',
        3 => 'Ashadh',
        4 => 'Shrawan',
        5 => 'Bhadra',
        6 => 'Ashwin',
        7 => 'Kartik',
        8 => 'Mangsir',
        9 => 'Poush',
        10 => 'Magh',
        11 => 'Falgun',
        12 => 'Chaitra',
    ];

    private const MONTHS_NE = [
        1 => 'बैशाख',
        2 => 'जेठ',
        3 => 'असार',
        4 => 'श्रावण',
        5 => 'भदौ',
        6 => 'असोज',
        7 => 'कार्तिक',
        8 => 'मंसिर',
        9 => 'पौष',
        10 => 'माघ',
        11 => 'फागुन',
        12 => 'चैत',
    ];

    private const WEEKDAYS_EN = [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ];

    private const WEEKDAYS_NE = [
        0 => 'आइत',
        1 => 'सोम',
        2 => 'मंगल',
        3 => 'बुध',
        4 => 'बिही',
        5 => 'शुक्र',
        6 => 'शनि',
    ];

    /**
     * @var array<string, array{year:int,month:int,day:int,weekday:int}>
     */
    private static array $cache = [];

    /**
     * @param CarbonInterface|DateTimeInterface|string $date
     * @return array{year:int,month:int,day:int,weekday:int}
     */
    public static function adToBs(CarbonInterface|DateTimeInterface|string $date): array
    {
        $adDate = self::normalizeDate($date);
        $cacheKey = $adDate->format('Y-m-d');
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $referenceAd = CarbonImmutable::parse(self::REFERENCE_AD, 'Asia/Kathmandu')->startOfDay();
        $offset = $referenceAd->diffInDays($adDate, false);

        $bsYear = self::REFERENCE_BS_YEAR;
        $bsMonth = self::REFERENCE_BS_MONTH;
        $bsDay = self::REFERENCE_BS_DAY;

        if ($offset > 0) {
            for ($i = 0; $i < $offset; $i++) {
                [$bsYear, $bsMonth, $bsDay] = self::incrementBs($bsYear, $bsMonth, $bsDay);
            }
        } elseif ($offset < 0) {
            for ($i = 0; $i < abs($offset); $i++) {
                [$bsYear, $bsMonth, $bsDay] = self::decrementBs($bsYear, $bsMonth, $bsDay);
            }
        }

        $result = [
            'year' => $bsYear,
            'month' => $bsMonth,
            'day' => $bsDay,
            'weekday' => $adDate->dayOfWeek,
        ];
        self::$cache[$cacheKey] = $result;

        return $result;
    }

    /**
     * @param CarbonInterface|DateTimeInterface|string $date
     */
    public static function format(CarbonInterface|DateTimeInterface|string $date, ?string $locale = null, bool $includeWeekday = false): string
    {
        $parts = self::adToBs($date);
        $localeCode = self::locale($locale);
        $monthLabel = $localeCode === 'ne'
            ? (self::MONTHS_NE[$parts['month']] ?? '')
            : (self::MONTHS_EN[$parts['month']] ?? '');

        $year = (string) $parts['year'];
        $day = (string) $parts['day'];
        if ($localeCode === 'ne') {
            $year = self::toNepaliDigits($year);
            $day = self::toNepaliDigits($day);
        }

        $suffix = $localeCode === 'ne' ? 'वि.सं.' : 'BS';
        $base = trim($monthLabel . ' ' . $day . ', ' . $year . ' ' . $suffix);
        if (!$includeWeekday) {
            return $base;
        }

        $weekday = $localeCode === 'ne'
            ? (self::WEEKDAYS_NE[$parts['weekday']] ?? '')
            : (self::WEEKDAYS_EN[$parts['weekday']] ?? '');

        return trim($weekday . ', ' . $base);
    }

    public static function toNepaliDigits(string $value): string
    {
        return strtr($value, [
            '0' => '०',
            '1' => '१',
            '2' => '२',
            '3' => '३',
            '4' => '४',
            '5' => '५',
            '6' => '६',
            '7' => '७',
            '8' => '८',
            '9' => '९',
        ]);
    }

    /**
     * @param CarbonInterface|DateTimeInterface|string $date
     */
    private static function normalizeDate(CarbonInterface|DateTimeInterface|string $date): CarbonImmutable
    {
        if ($date instanceof CarbonInterface || $date instanceof DateTimeInterface) {
            return CarbonImmutable::instance($date)->setTimezone('Asia/Kathmandu')->startOfDay();
        }

        return CarbonImmutable::parse((string) $date, 'Asia/Kathmandu')->startOfDay();
    }

    private static function locale(?string $locale): string
    {
        $normalized = strtolower(trim((string) ($locale ?? app()->getLocale())));
        return $normalized === 'ne' ? 'ne' : 'en';
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function incrementBs(int $year, int $month, int $day): array
    {
        $day++;
        if ($day > self::monthDays($year, $month)) {
            $day = 1;
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }

        return [$year, $month, $day];
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function decrementBs(int $year, int $month, int $day): array
    {
        $day--;
        if ($day < 1) {
            $month--;
            if ($month < 1) {
                $month = 12;
                $year--;
            }
            $day = self::monthDays($year, $month);
        }

        return [$year, $month, $day];
    }

    private static function monthDays(int $year, int $month): int
    {
        if (!isset(self::BS_MONTH_DAYS[$year])) {
            throw new OutOfRangeException('Nepali date year is out of supported range: ' . $year);
        }

        if ($month < 1 || $month > 12) {
            throw new OutOfRangeException('Invalid Nepali date month: ' . $month);
        }

        return (int) self::BS_MONTH_DAYS[$year][$month - 1];
    }
}

