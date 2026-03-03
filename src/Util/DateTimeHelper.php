<?php

namespace HeimrichHannot\FlareBundle\Util;

use Contao\Config;

class DateTimeHelper
{
    private const TIME_SPANS = [
        'day' => [
            'yesterday' => 'yesterday',
            'now' => 'now',
            'today' => 'today',
            'tomorrow' => 'tomorrow',
        ],
        'week' => [
            'last_week' => 'monday last week',
            'this_week' => 'monday this week',
            'next_week' => 'monday next week',
        ],
        'month' => [
            'last_month' => 'first day of last month',
            'this_month' => 'first day of this month',
            'next_month' => 'first day of next month',
        ],
        'year' => [
            'next_year' => 'first day of next year',
            'this_year' => 'first day of this year',
            'last_year' => 'first day of last year',
        ],
        'relative_future' => [
            'in_1_week' => '+1 week',
            'in_2_weeks' => '+2 week',
            'in_3_weeks' => '+3 week',
            'in_1_month' => '+1 month',
            'in_2_months' => '+2 month',
            'in_3_months' => '+3 month',
            'in_1_year' => '+1 year',
            'in_2_years' => '+2 year',
        ],
        'relative_past' => [
            '1_week_ago' => '-1 week',
            '2_weeks_ago' => '-2 week',
            '3_weeks_ago' => '-3 week',
            '1_month_ago' => '-1 month',
            '2_months_ago' => '-2 month',
            '3_months_ago' => '-3 month',
            '1_year_ago' => '-1 year',
            '2_years_ago' => '-2 year',
        ],
    ];

    private static \DateTimeZone $timeZone;

    private static array $timeSpanMap;

    /**
     * Get the configured time zone or the system default.
     *
     * @mago-expect lint:no-nested-ternary The config fallback chain has to test for truthiness.
     */
    public static function getTimeZone(): \DateTimeZone
    {
        return self::$timeZone ??= new \DateTimeZone(
            Config::get('timeZone') ?: \date_default_timezone_get() ?: 'UTC'
        );
    }

    /**
     * Get all time span options as a flat array.
     */
    public static function getTimeSpanOptions(): array
    {
        return \array_map('\array_keys', self::TIME_SPANS);
    }

    public static function getTimeSpanMap(): array
    {
        if (isset(self::$timeSpanMap)) {
            return self::$timeSpanMap;
        }

        $map = [];

        foreach (self::TIME_SPANS as $options)
        {
            $map += $options;
        }

        return self::$timeSpanMap = $map;
    }

    public static function maxTimestamp(): int
    {
        return min(\PHP_INT_MAX, 4294967295);
    }

    public static function spanToTimeString(string $timeSpan): ?string
    {
        return self::getTimeSpanMap()[$timeSpan] ?? null;
    }

    public static function toTimestamp(string $time_span_or_string_or_stamp): ?int
    {
        if (\is_numeric($time_span_or_string_or_stamp)) {
            return (int) $time_span_or_string_or_stamp;
        }

        $timeString = self::spanToTimeString($time_span_or_string_or_stamp) ?? $time_span_or_string_or_stamp;

        return \strtotime($timeString) ?: null;
    }

    public static function timestampToDateTime(int|string|null $timestamp): ?\DateTime
    {
        if (\is_null($timestamp)) {
            return null;
        }

        return \DateTime::createFromFormat('U', (string) $timestamp)?->setTimezone(self::getTimeZone()) ?: null;
    }

    public static function toDateTime(string $time_span_or_string_or_stamp): ?\DateTime
    {
        return static::timestampToDateTime(
            self::toTimestamp($time_span_or_string_or_stamp)
        );
    }
}