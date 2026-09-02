<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PublishedFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('published_field')->default(null)->allowedTypes('null', 'string');
        $resolver->define('start_field')->default(null)->allowedTypes('null', 'string');
        $resolver->define('stop_field')->default(null)->allowedTypes('null', 'string');
        $resolver->define('invert_published')->default(false)->allowedTypes('bool');
        $resolver->define('now')->required()->allowedTypes('int');
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
        if ($options['published_field'])
        {
            $publishedField = $builder->column($options['published_field']);
            $operator = $options['invert_published'] ? 'neq' : 'eq';

            $builder->where($builder->expr()->{$operator}($publishedField, ':published'))
                ->setParameter('published', '1');
        }

        if ($options['start_field'])
        {
            $startField = $builder->column($options['start_field']);

            $builder->where("{$startField} = '' OR {$startField} = '0' OR {$startField} <= :start")
                ->setParameter('start', $options['now']);
        }

        if ($options['stop_field'])
        {
            $stopField = $builder->column($options['stop_field']);

            $builder->where("{$stopField} = '' OR {$stopField} = '0' OR {$stopField} >= :stop")
                ->setParameter('stop', $options['now']);
        }
    }
}