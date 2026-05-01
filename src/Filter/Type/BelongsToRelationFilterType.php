<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BelongsToRelationFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field_pid')->required()->allowedTypes('string');
        $resolver->define('field_dynamic_ptable')->default(null)->allowedTypes('null', 'string');
        $resolver->define('whitelist')->default([])->allowedTypes('array');
        $resolver->define('parent_groups')->default([])->allowedTypes('array');
        $resolver->define('submitted_data')->default(null)->allowedTypes('null', 'array');
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
        if ($options['field_dynamic_ptable']) {
            $this->buildDynamicQuery($builder, $options);
            return;
        }

        if (!$options['whitelist']) {
            $builder::abort();
        }

        $builder->where($builder->expr()->in($builder->column($options['field_pid']), ':whitelist'))
            ->setParameter('whitelist', $options['whitelist']);
    }

    private function buildDynamicQuery(FilterQueryBuilder $builder, array $options): void
    {
        $ors = [];
        $submittedData = $options['submitted_data'];
        $fieldDynamicPtable = $options['field_dynamic_ptable'];
        $fieldPid = $options['field_pid'];

        $colDynamicPtable = $builder->column($fieldDynamicPtable);
        $colPid = $builder->column($fieldPid);

        foreach (\array_values($options['parent_groups']) as $i => $group)
        {
            $table = $group['table'] ?? null;
            $parentIds = $group['ids'] ?? null;

            if (!$table || !\is_array($parentIds)) {
                continue;
            }

            if (\is_array($submittedData))
            {
                $submittedWhitelist = $submittedData[$table] ?? null;

                if (!\is_array($submittedWhitelist)) {
                    continue;
                }

                $parentIds = \array_intersect($parentIds, $submittedWhitelist);
            }

            $parentIds = \array_values(\array_filter($parentIds));

            if (!$parentIds) {
                continue;
            }

            $tableParam = \sprintf(':g%s_ptable', $i);
            $idsParam = \sprintf(':g%s_whitelist', $i);

            $ors[] = $builder->expr()->and(
                $builder->expr()->eq($colDynamicPtable, $tableParam),
                $builder->expr()->in($colPid, $idsParam)
            );

            $builder->setParameter($tableParam, $table);
            $builder->setParameter($idsParam, $parentIds);
        }

        if (!$ors) {
            $builder::abort();
        }

        if (\count($ors) === 1) {
            $builder->where($ors[0]);
            return;
        }

        $builder->whereOr(...$ors);
    }
}