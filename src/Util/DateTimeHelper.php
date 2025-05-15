<?php

namespace HeimrichHannot\FlareBundle\Util;

use DateTimeImmutable;

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

    public static function getTimeSpanOptions(): array
    {
        return \array_map(static fn ($timeSpanOptions) => \array_keys($timeSpanOptions), self::TIME_SPANS);
    }

    public static function getTimeSpanMap(): array
    {
        $options = [];

        foreach (self::TIME_SPANS as $timeSpanOptions)
        {
            $options += $timeSpanOptions;
        }

        return $options;
    }

    public static function maxTimestamp(): int
    {
        return min(\PHP_INT_MAX, 4294967295);
    }

    public static function getTimeString(string $timeSpan): ?string
    {
        return self::getTimeSpanMap()[$timeSpan] ?? null;
    }

    public static function getTimestamp(string $timeSpan): ?int
    {
        if (\is_numeric($timeSpan)) {
            return (int) $timeSpan;
        }

        if (!$timeString = self::getTimeString($timeSpan)) {
            return null;
        }

        return \strtotime($timeString) ?: null;
    }

    public static function timestampToDateTime(int|string $timestamp): ?\DateTimeInterface
    {
        return \DateTime::createFromFormat('U', (string) $timestamp) ?: null;
    }

    public static function getDateTime(string $timeSpan): ?\DateTimeInterface
    {
        if (\is_numeric($timeSpan)) {
            return self::timestampToDateTime($timeSpan);
        }

        if (!$timestamp = self::getTimestamp($timeSpan)) {
            return null;
        }

        return static::timestampToDateTime($timestamp);
    }
}