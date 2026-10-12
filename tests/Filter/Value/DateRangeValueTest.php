<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Value;

use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use PHPUnit\Framework\TestCase;

final class DateRangeValueTest extends TestCase
{
    /**
     * The reason the value object stores `int` rather than `\DateTimeInterface`: the probe measured
     * that the same instant hashes differently as `+01:00` (timezone_type 1) and `Europe/Berlin`
     * (timezone_type 3). Reducing to a timestamp at construction makes the two indistinguishable,
     * which is exactly §9's "anything with multiple equal representations is normalized in the
     * constructor".
     */
    public function testEquivalentTimezoneRepresentationsNormaliseToOneValue(): void
    {
        $offset = new \DateTimeImmutable('2026-01-01 12:00:00', new \DateTimeZone('+01:00'));
        $named = new \DateTimeImmutable('2026-01-01 12:00:00', new \DateTimeZone('Europe/Berlin'));

        self::assertSame($offset->getTimestamp(), $named->getTimestamp(), 'precondition: same instant');

        // The hazard, still live for the raw objects.
        self::assertNotSame(\serialize($offset), \serialize($named));

        // Gone once reduced to a timestamp.
        self::assertSame(
            DateRangeValue::tryFrom($offset, null)?->from,
            DateRangeValue::tryFrom($named, null)?->from,
        );
    }

    public function testTryFromAcceptsDateTimeIntFloatAndNumericString(): void
    {
        self::assertSame(1_767_225_600, DateRangeValue::tryFrom(1_767_225_600, null)?->from);
        self::assertSame(1_767_225_600, DateRangeValue::tryFrom('1767225600', null)?->from);
        self::assertSame(1_767_225_600, DateRangeValue::tryFrom(' 1767225600 ', null)?->from);
        self::assertSame(1_767_225_600, DateRangeValue::tryFrom(1_767_225_600.9, null)?->from);
        self::assertSame(
            1_767_225_600,
            DateRangeValue::tryFrom((new \DateTimeImmutable())->setTimestamp(1_767_225_600), null)?->from,
        );
    }

    /**
     * Free-form date strings are rejected deliberately: `new \DateTimeImmutable($input)` throws on
     * malformed input and is non-deterministic for relative expressions such as 'now'. Programmatic
     * callers pre-resolve with DateTimeHelper::toTimestamp().
     */
    public function testTryFromRejectsFreeFormStringsAndOtherJunk(): void
    {
        self::assertNull(DateRangeValue::tryFrom('2026-01-01', null));
        self::assertNull(DateRangeValue::tryFrom('now', null));
        self::assertNull(DateRangeValue::tryFrom('garbage', null));
        self::assertNull(DateRangeValue::tryFrom(\NAN, null));
        self::assertNull(DateRangeValue::tryFrom([], null));
    }

    public function testTryFromReturnsNullOnlyWhenBothBoundsAreAbsent(): void
    {
        self::assertNull(DateRangeValue::tryFrom(null, null));
        self::assertSame(5, DateRangeValue::tryFrom(5, null)?->from);
        self::assertNull(DateRangeValue::tryFrom(5, null)?->to);
        self::assertSame(9, DateRangeValue::tryFrom(null, 9)?->to);
    }

    /**
     * `0` is the epoch, not "absent" — expressible because absence is `null`. This diverges on
     * purpose from `CalendarCurrentFilterElement::mixedToDateTime()`, whose `if (!$input)` guard
     * discards `0` and `'0'`.
     */
    public function testEpochIsAValueNotAnAbsence(): void
    {
        $value = DateRangeValue::tryFrom(0, null);

        self::assertNotNull($value);
        self::assertSame(0, $value->from);
    }

    /**
     * An inverted range is legal and yields no rows; the form validates it separately with a
     * POST_SUBMIT FormError. Silently swapping the bounds here would change behaviour.
     */
    public function testInvertedRangeIsPreserved(): void
    {
        $value = DateRangeValue::tryFrom(100, 50);

        self::assertSame(100, $value?->from);
        self::assertSame(50, $value?->to);
    }
}
