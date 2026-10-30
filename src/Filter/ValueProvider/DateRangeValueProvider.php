<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\ValueProvider;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Value\DateRangeValue;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Stores a serialized `[from, to]` of time expressions, each null for an open bound. An expression is anything
 * {@see DateTimeHelper::toTimestamp()} reads: a timestamp, a time span key, or a `strtotime()` string such as
 * `today`, `+1 week` or `2026-10-26`. Expressions are resolved whenever the value is provided, so relative ones
 * move with the current time.
 */
final readonly class DateRangeValueProvider implements ValueProviderInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    public function provide(mixed $stored, FilterElementInterface $element): ?DateRangeValue
    {
        if ($stored === null || $stored === '' || $stored === []) {
            return null;
        }

        $range = \is_array($stored) ? $stored : self::unserialize($stored);

        if (!\array_is_list($range) || \count($range) > 2) {
            throw new UnreadableValueException('The stored date range must be a list of two time expressions.');
        }

        [$from, $to] = [self::toTimestamp($range[0] ?? null), self::toTimestamp($range[1] ?? null)];

        return DateRangeValue::tryFrom($from, $to);
    }

    /**
     * Two free text inputs without `rgxp`: Contao would convert a `date` field to a timestamp before the save
     * callbacks run, which neither works for a `multiple` field nor allows relative expressions.
     */
    public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void
    {
        $field
            ->inputType('text')
            ->label(
                $this->translator->trans('filter.value.date_range.label', [], 'flare'),
                $this->translator->trans('filter.value.date_range.description', [], 'flare'),
            )
            ->eval(['multiple' => true, 'size' => 2, 'tl_class' => 'w50'])
            ->save($this->normalizeExpressions(...));
    }

    /**
     * @throws \RuntimeException If an expression cannot be resolved; Contao shows the message at the field.
     */
    private function normalizeExpressions(mixed $value): ?string
    {
        $range = StringUtil::deserialize($value, true);

        $expressions = \array_map(
            static fn (mixed $entry): ?string => \is_scalar($entry) && \trim((string) $entry) !== ''
                ? \trim((string) $entry)
                : null,
            \array_pad(\array_slice(\array_values($range), 0, 2), 2, null),
        );

        foreach ($expressions as $expression)
        {
            if ($expression !== null && DateTimeHelper::toTimestamp($expression) === null)
            {
                throw new \RuntimeException($this->translator->trans(
                    'filter.value.date_range.invalid',
                    ['%expression%' => $expression],
                    'flare',
                ));
            }
        }

        return $expressions === [null, null] ? null : \serialize($expressions);
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

        if (\is_int($entry)) {
            return $entry;
        }

        if (\is_string($entry) && ($timestamp = DateTimeHelper::toTimestamp(\trim($entry))) !== null) {
            return $timestamp;
        }

        throw new UnreadableValueException(\sprintf(
            'The stored date range entry %s is neither a timestamp nor a time expression.',
            \json_encode($entry),
        ));
    }
}
