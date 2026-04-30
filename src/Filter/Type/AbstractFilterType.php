<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\Filter\FilterBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AbstractFilterType implements FilterTypeInterface
{
    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function buildFilter(FilterBuilder $builder, FilterInvocation $inv): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
    }
}