<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use Doctrine\DBAL\ParameterType;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BooleanFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->required()->allowedTypes('string');
        $resolver->define('value')->required()->allowedTypes('bool');
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
        $builder->where($builder->expr()->eq($builder->column($options['field']), ':val'))
            ->setParameter('val', $options['value'] ? '1' : '', ParameterType::STRING);
    }
}