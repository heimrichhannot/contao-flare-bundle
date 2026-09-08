<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Logic;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IntegerIdChoiceLogic extends AbstractLogic
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->default('id')->allowedTypes('string');
        $resolver->define('ids')->required()->allowedTypes('array');
    }

    public function buildConditions(FilterConditionsBuilder $builder, array $options): void
    {
        $ids = \array_values(\array_unique(\array_filter(\array_map('\intval', $options['ids']))));

        if (!$ids) {
            return;
        }

        if (\count($ids) === 1) {
            $builder->where($builder->expr()->eq($builder->column($options['field']), ':id'))
                ->setParameter('id', \reset($ids), ParameterType::INTEGER);
            return;
        }

        $builder->where($builder->expr()->in($builder->column($options['field']), ':ids'))
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER);
    }
}
