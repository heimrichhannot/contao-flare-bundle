<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AutoconfigureTag(self::FLARE_FILTER_TYPE_TAG)]
interface FilterTypeInterface
{
    public const FLARE_FILTER_TYPE_TAG = 'flare.filter_type';

    /**
     * Configures the options for this type.
     */
    public function configureOptions(OptionsResolver $resolver): void;

    /**
     * Builds the filter query.
     *
     * @param array<string, mixed> $options
     */
    public function buildQuery(FilterQueryBuilder $builder, array $options): void;
}
