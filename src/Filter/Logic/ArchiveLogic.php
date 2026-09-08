<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Logic;

use Doctrine\DBAL\ArrayParameterType;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArchiveLogic extends AbstractLogic
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->default('pid')->allowedTypes('string');
        $resolver->define('parent_ids')->required()->allowedTypes('array');
    }

    public function buildConditions(FilterConditionsBuilder $builder, array $options): void
    {
        $ids = \array_values(\array_unique(\array_filter(\array_map('\intval', $options['parent_ids']))));

        if (!$ids) {
            throw new FilterException('No valid parent archive ids extracted.', method: __METHOD__);
        }

        $builder->where($builder->expr()->in($builder->column($options['field']), ':pids'))
            ->setParameter('pids', $ids, ArrayParameterType::INTEGER);
    }
}
