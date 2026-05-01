<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalendarCurrentFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('start')->required()->allowedTypes('int');
        $resolver->define('stop')->required()->allowedTypes('int');
        $resolver->define('has_extended_events')->default(false)->allowedTypes('bool');
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
        $colStartTime = $builder->column('startTime');
        $colRepeatEnd = $builder->column('repeatEnd');
        $colRecurrences = $builder->column('recurrences');
        $colRecurring = $builder->column('recurring');

        $or = [
            "{$colStartTime} >= :start AND {$colStartTime} <= :end",
            $builder->expr()->and(
                $builder->expr()->eq($colRecurring, '1'),
                $builder->expr()->lte($colStartTime, ':end'),
                $builder->expr()->or(
                    $builder->expr()->eq($colRecurrences, '0'),
                    $builder->expr()->gte($colRepeatEnd, ':start'),
                ),
            ),
        ];

        if ($options['has_extended_events'])
        {
            $colEndTime = $builder->column('endTime');

            $or[] = "{$colEndTime} >= :start AND {$colEndTime} <= :end";
            $or[] = "{$colStartTime} <= :start AND {$colEndTime} >= :end";
        }

        $builder->whereOr(...$or);
        $builder->setParameter('start', $options['start']);
        $builder->setParameter('end', $options['stop']);
    }
}