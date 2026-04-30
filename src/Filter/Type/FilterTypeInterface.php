<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\Filter\FilterBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

interface FilterTypeInterface
{
    /**
     * Configures the options for this type.
     */
    public function configureOptions(OptionsResolver $resolver): void;

    /**
     * Builds the filter.
     */
    public function buildFilter(FilterBuilder $builder, FilterInvocation $inv): void;

    /**
     * Builds the filter query.
     *
     * @param array<string, mixed> $options
     */
    public function buildQuery(FilterQueryBuilder $builder, array $options): void;
}