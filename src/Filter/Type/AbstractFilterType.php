<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

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
    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
    }
}