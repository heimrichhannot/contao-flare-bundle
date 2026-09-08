<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

/**
 * An inclusive date/time range as unix timestamps: the value consumed by `DateRangeFilterElement`
 * and `CalendarCurrentFilterElement`.
 *
 * Timestamps, not `\DateTimeInterface`, for three reasons:
 *   1. `serialize()` embeds `date`/`timezone_type`/`timezone`, so the same instant hashes
 *      differently as `+01:00` (type 1) and `Europe/Berlin` (type 3) — measured in
 *      tests/Filter/ValueObjectSerializeProbeTest.php. SPEC_FILTER_FORMS.md §9 allows either a
 *      timestamp or a normalised timezone, but normalising needs
 *      {@see \HeimrichHannot\FlareBundle\Util\DateTimeHelper::getTimeZone()}, which reads
 *      `Contao\Config` and would make this class unconstructible without a booted framework.
 *   2. Nothing is lost: `DateRangeFilterType::buildQuery()` and
 *      `CalendarCurrentFilterElement::buildFilter()` both unwrap to `getTimestamp()` at once.
 *   3. One representation per instant, comparable and sortable.
 *
 * `from > to` is *not* corrected here. It is a legal, empty-result range; the form validates it
 * separately (a POST_SUBMIT FormError today), and silently swapping would change behaviour.
 *
 * `0` is a meaningful timestamp (the epoch), expressible because "absent" is `null` — a deliberate
 * divergence from `CalendarCurrentFilterElement::mixedToDateTime()`, whose `if (!$input)` guard
 * discards `0` and `'0'`.
 */
final readonly class DateRangeValue implements ValueInterface
{
    private function __construct(
        public ?int $from = null,
        public ?int $to = null,
    ) {}

    /**
     * @param mixed $from `\DateTimeInterface`, an int/float timestamp, a numeric string, or null.
     * @param mixed $to Likewise.
     *
     * Free-form date strings are deliberately rejected: they throw on malformed input and are
     * non-deterministic for relative expressions such as `'now'`. Programmatic callers pre-resolve
     * them with {@see \HeimrichHannot\FlareBundle\Util\DateTimeHelper::toTimestamp()}, which also
     * understands the span keywords and needs no framework boot.
     */
    public static function tryFrom(mixed $from, mixed $to): ?self
    {
        $from = self::toTimestamp($from);
        $to = self::toTimestamp($to);

        if ($from === null && $to === null) {
            return null;
        }

        return new self($from, $to);
    }

    private static function toTimestamp(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (\is_int($value)) {
            return $value;
        }

        if (\is_float($value)) {
            return \is_finite($value) ? (int) $value : null;
        }

        if (\is_string($value) && \is_numeric($value = \trim($value))) {
            return (int) $value;
        }

        return null;
    }
}
