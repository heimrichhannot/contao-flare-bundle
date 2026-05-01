<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FieldValueChoiceFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->required()->allowedTypes('string');
        $resolver->define('values')->required()->allowedTypes('array');
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
        $values = $options['values'];

        if (!$values) {
            return;
        }

        $field = $builder->column($options['field']);

        if (\count($values) < 2)
        {
            $builder->where("LOWER(TRIM({$field})) = :value")
                ->setParameter('value', \reset($values));
            return;
        }

        $builder->where("LOWER(TRIM({$field})) IN (:values)")
            ->setParameter('values', $values);
    }
}