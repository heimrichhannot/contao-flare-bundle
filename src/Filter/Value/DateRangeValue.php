<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;

/**
 * Represents a date range with optional start and end dates for filtering.
 * - `from > to` is *not* corrected here. It is a legal, empty-result range; forms validate it separately.
 * - `0` is a meaningful timestamp (the epoch), expressible because "absent" is `null`.
 */
final readonly class DateRangeValue implements ValueInterface
{
    private function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {}

    /**
     * @param mixed<DateTimeInterface|int|float|string|null> $from
     * @param mixed<DateTimeInterface|int|float|string|null> $to
     */
    public static function tryFrom(mixed $from, mixed $to, ?DateTimeZone $timezone = null): ?self
    {
        $timezone ??= DateTimeHelper::getTimeZone();

        $from = self::toDateTime($from, $timezone);
        $to = self::toDateTime($to, $timezone);

        if ($from === null && $to === null) {
            return null;
        }

        return new self($from, $to);
    }

    private static function toDateTime(mixed $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)
                ->setTimezone($timezone);
        }

        if (\is_int($value)) {
            return self::createDateTimeFromTimestamp(\sprintf('%d.000000', $value), $timezone);
        }

        if (\is_float($value)) {
            if (!\is_finite($value)) {
                return null;
            }

            return self::createDateTimeFromTimestamp(\sprintf('%.6F', $value), $timezone);
        }

        if (\is_string($value) && \is_numeric($value = \trim($value))) {
            return self::createDateTimeFromTimestamp(\sprintf('%.6F', (float) $value), $timezone);
        }

        return null;
    }

    private static function createDateTimeFromTimestamp(string $timestamp, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $dateTime = DateTimeImmutable::createFromFormat('U.u', $timestamp);

        return $dateTime instanceof DateTimeImmutable
            ? $dateTime->setTimezone($timezone)
            : null;
    }
}
