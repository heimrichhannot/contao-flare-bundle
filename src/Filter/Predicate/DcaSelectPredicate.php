<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Predicate;

use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DcaSelectPredicate extends AbstractPredicate
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->required()->allowedTypes('string');
        $resolver->define('selected')->required()->allowedTypes('array');
        $resolver->define('valid_options')->required()->allowedTypes('array');
        $resolver->define('is_serialized')->default(false)->allowedTypes('bool');
    }

    public function buildConditions(FilterConditionsBuilder $builder, array $options): void
    {
        $selected = \array_values($options['selected']);
        $validOptions = $options['valid_options'];
        $field = $options['field'];

        if (!$selected) {
            return;
        }

        if (!$validOptions || !$field) {
            $builder::abort();
        }

        if (\count($selected) === 1)
        {
            $value = \current($selected);
            if (!\array_key_exists($value, $validOptions)) {
                $builder::abort();
            }

            if ($options['is_serialized']) {
                $builder->whereInSerialized($value, $field);
                return;
            }

            $builder->where($builder->expr()->eq($builder->column($field), ':value'))
                ->setParameter('value', $value);
            return;
        }

        $filtered = [];
        foreach ($selected as $value)
        {
            if (\array_key_exists($value, $validOptions)) {
                $filtered[] = $value;
            }
        }

        if (!$filtered) {
            $builder::abort();
        }

        if ($options['is_serialized']) {
            $builder->whereInSerialized($filtered, $field);
            return;
        }

        $builder->where($builder->expr()->in($builder->column($field), ':values'))
            ->setParameter('values', $filtered);
    }
}
