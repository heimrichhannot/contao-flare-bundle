<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateRangeFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->required()->allowedTypes('string');
        $resolver->define('from')->default(null)->allowedTypes('null', \DateTimeInterface::class);
        $resolver->define('to')->default(null)->allowedTypes('null', \DateTimeInterface::class);
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
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