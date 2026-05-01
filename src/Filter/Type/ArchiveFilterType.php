<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use Doctrine\DBAL\ArrayParameterType;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArchiveFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->default('pid')->allowedTypes('string');
        $resolver->define('parent_ids')->required()->allowedTypes('array');
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
        $ids = \array_values(\array_unique(\array_filter(\array_map('\intval', $options['parent_ids']))));

        if (!$ids) {
            throw new FilterException('No valid parent archive ids extracted.');
        }

        $builder->where($builder->expr()->in($builder->column($options['field']), ':pids'))
            ->setParameter('pids', $ids, ArrayParameterType::INTEGER);
    }
}