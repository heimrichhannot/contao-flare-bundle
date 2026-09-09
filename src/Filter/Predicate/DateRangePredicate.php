<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Predicate;

use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateRangePredicate extends AbstractPredicate
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->required()->allowedTypes('string');
        $resolver->define('from')->default(null)->allowedTypes('null', \DateTimeInterface::class);
        $resolver->define('to')->default(null)->allowedTypes('null', \DateTimeInterface::class);
    }

    public function buildConditions(FilterConditionsBuilder $builder, array $options): void
    {
        $field = $builder->column($options['field']);

        if ($options['from'] instanceof \DateTimeInterface) {
            $builder->where($builder->expr()->gte($field, ':from'))
                ->setParameter('from', $options['from']->getTimestamp());
        }

        if ($options['to'] instanceof \DateTimeInterface) {
            $builder->where($builder->expr()->lte($field, ':to'))
                ->setParameter('to', $options['to']->getTimestamp());
        }
    }
}
