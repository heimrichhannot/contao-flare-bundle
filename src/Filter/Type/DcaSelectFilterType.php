<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DcaSelectFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->required()->allowedTypes('string');
        $resolver->define('selected')->required()->allowedTypes('array');
        $resolver->define('valid_options')->required()->allowedTypes('array');
        $resolver->define('is_multiple_dca_field')->default(false)->allowedTypes('bool');
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
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

            if ($options['is_multiple_dca_field']) {
                $builder->whereInSerialized($value, $field);
                return;
            }

            $builder->where($builder->expr()->eq($builder->column($field), ':value'))
                ->setParameter('value', $value);
            return;
        }

        if (\count(\array_unique($validOptions)) !== \count($validOptions)) {
            throw new FilterException('The options for the DCA select field must be unique.');
        }

        $filtered = [];
        foreach ($selected as $value)
        {
            if ($validOptions[$value] ?? null) {
                $filtered[] = $value;
            }
        }

        if (!$filtered) {
            $builder::abort();
        }

        if ($options['is_multiple_dca_field']) {
            $builder->whereInSerialized($filtered, $field);
            return;
        }

        $builder->where($builder->expr()->in($builder->column($field), ':values'))
            ->setParameter('values', $filtered);
    }
}