<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Predicate;

use DateTimeInterface;
use Doctrine\DBAL\ParameterType;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Matches the rows whose start column lies in the period `start`–`stop` (Unix timestamps), or, with `overlap` and an
 * end column, whose time span overlaps it; with the recurrence columns, also the recurring rows by Contao's
 * approximation. A row with an empty start (`NULL`, `''`, `0`) never matches; an empty end is no end; with an end
 * column, a row whose end lies before its start never matches. The columns in `cast_fields` are read as numbers.
 */
class TimeWindowPredicate extends AbstractPredicate
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('start')
            ->required()
            ->allowedTypes('int', DateTimeInterface::class)
            ->normalize($this->normalizeTimestamp(...));

        $resolver->define('stop')
            ->required()
            ->allowedTypes('int', DateTimeInterface::class)
            ->normalize($this->normalizeTimestamp(...));

        $resolver->define('start_field')->required()->allowedTypes('string');
        $resolver->define('stop_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('overlap')->default(false)->allowedTypes('bool');

        $resolver->define('recurring_field')
            ->default(null)
            ->allowedTypes('string', 'null')
            ->normalize($this->requireRecurrenceColumns(...));

        $resolver->define('recurrences_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('repeat_end_field')->default(null)->allowedTypes('string', 'null');
        $resolver->define('cast_fields')->default([])->allowedTypes('string[]');
    }

    public function buildConditions(FilterConditionsBuilder $builder, array $options): void
    {
        $column = static fn (string $name): string => \in_array($name, $options['cast_fields'], true)
            ? "CAST({$builder->column($name)} AS SIGNED)"
            : $builder->column($name);

        $start = $column($options['start_field']);
        $end = $options['stop_field'] !== null ? $column($options['stop_field']) : null;

        $condition = "{$start} IS NOT NULL AND {$start} <> 0";

        if ($end !== null) {
            $condition .= " AND ({$end} IS NULL OR {$end} = 0 OR {$end} >= {$start})";
        }

        $branches = $end !== null && $options['overlap']
            ? "({$start} <= :stop AND CASE WHEN {$end} IS NULL OR {$end} = 0 THEN {$start} ELSE {$end} END >= :start)"
            : "({$start} BETWEEN :start AND :stop)";

        if ($options['recurring_field'] !== null)
        {
            $recurring = $column($options['recurring_field']);
            $recurrences = $column($options['recurrences_field']);
            $repeatEnd = $column($options['repeat_end_field']);

            $branches .= " OR ({$recurring} IS NOT NULL AND {$recurring} <> 0 AND {$start} <= :stop"
                . " AND (COALESCE({$recurrences}, 0) = 0 OR ({$recurrences} > 0 AND {$repeatEnd} >= :start)))";
        }

        $builder->where("{$condition} AND ({$branches})");
        $builder->setParameter('start', $options['start'], ParameterType::INTEGER);
        $builder->setParameter('stop', $options['stop'], ParameterType::INTEGER);
    }

    private function normalizeTimestamp(Options $options, mixed $value): int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        return (int) $value;
    }

    /**
     * @throws InvalidOptionsException If the recurring flag is set without the other two recurrence columns.
     */
    private function requireRecurrenceColumns(Options $options, ?string $value): ?string
    {
        if ($value !== null && ($options['recurrences_field'] === null || $options['repeat_end_field'] === null)) {
            throw new InvalidOptionsException(
                'The options "recurrences_field" and "repeat_end_field" are required with "recurring_field".'
            );
        }

        return $value;
    }
}
