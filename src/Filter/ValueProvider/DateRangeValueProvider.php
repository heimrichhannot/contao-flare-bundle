<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\ValueProvider;

use Contao\Date;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;

/**
 * Stores a serialized `[fromTimestamp|null, toTimestamp|null]`.
 */
final readonly class DateRangeValueProvider implements ValueProviderInterface
{
    public function provide(mixed $stored, FilterElementInterface $element): ?DateRangeValue
    {
        if ($stored === null || $stored === '' || $stored === []) {
            return null;
        }

        $range = \is_array($stored) ? $stored : self::unserialize($stored);

        if (!\array_is_list($range) || \count($range) > 2) {
            throw new UnreadableValueException('The stored date range must be a list of two timestamps.');
        }

        [$from, $to] = [self::toTimestamp($range[0] ?? null), self::toTimestamp($range[1] ?? null)];

        return DateRangeValue::tryFrom($from, $to);
    }

    public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void
    {
        $field
            ->inputType('text')
            ->eval(['multiple' => true, 'size' => 2, 'rgxp' => 'date', 'tl_class' => 'w50'])
            ->load($this->timestampsToDates(...))
            ->save($this->datesToTimestamps(...));
    }

    /**
     * Contao does not convert the values of a `multiple` text field itself.
     */
    private function timestampsToDates(mixed $value): mixed
    {
        $range = StringUtil::deserialize($value);

        if (!\is_array($range)) {
            return $value;
        }

        $dates = \array_map(
            static fn (mixed $ts): string => \is_numeric($ts)
                ? Date::parse(Date::getNumericDateFormat(), (int) $ts)
                : '',
            \array_slice(\array_values($range), 0, 2),
        );

        return \serialize($dates);
    }

    private function datesToTimestamps(mixed $value): mixed
    {
        $range = StringUtil::deserialize($value);

        if (!\is_array($range)) {
            return $value;
        }

        $timestamps = \array_map(
            static fn (mixed $date): ?int => \is_string($date) && \trim($date) !== ''
                // Contao's Date accepts a date string; its docblock wrongly declares an integer.
                // @phpstan-ignore argument.type
                ? (new Date(\trim($date), Date::getNumericDateFormat()))->tstamp
                : null,
            \array_slice(\array_values($range), 0, 2),
        );

        return $timestamps === [null, null] || $timestamps === [null] ? null : \serialize($timestamps);
    }

    /**
     * @return array<mixed>
     */
    private static function unserialize(mixed $stored): array
    {
        $range = \is_string($stored) ? StringUtil::deserialize($stored) : null;

        if (!\is_array($range)) {
            throw new UnreadableValueException('The stored date range is not a serialized array.');
        }

        return $range;
    }

    private static function toTimestamp(mixed $entry): ?int
    {
        if ($entry === null || $entry === '') {
            return null;
        }

        if (\is_int($entry) || (\is_string($entry) && \preg_match('/^-?\d+$/', $entry))) {
            return (int) $entry;
        }

        throw new UnreadableValueException(\sprintf(
            'The stored date range entry %s is not a timestamp.',
            \json_encode($entry),
        ));
    }
}
