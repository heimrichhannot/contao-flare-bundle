<?php

namespace HeimrichHannot\FlareBundle\Util;

class DateTimeHelper
{
    public const TIME_SPAN_MAP = [
        'now' => 'now',
        'today' => 'today',
        'tomorrow' => 'tomorrow',
        'yesterday' => 'yesterday',
        'this_week' => 'first day of this week',
        'next_week' => 'first day of next week',
        'last_week' => 'first day of last week',
        'this_month' => 'first day of this month',
        'next_month' => 'first day of next month',
        'last_month' => 'first day of last month',
        'this_year' => 'first day of this year',
        'next_year' => 'first day of next year',
        'last_year' => 'first day of last year',
    ];

    public static function getTimeString(string $timeSpan): ?string
    {
        return self::TIME_SPAN_MAP[$timeSpan] ?? null;
    }

    public static function getTimestamp(string $timeSpan): ?int
    {
        if (!$timeString = self::getTimeString($timeSpan)) {
            return null;
        }

        return \strtotime($timeString) ?: null;
    }
}