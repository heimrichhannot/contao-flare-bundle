<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Predicate;

use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AutoconfigureTag(self::FLARE_FILTER_PREDICATE_TAG)]
interface PredicateInterface
{
    public const FLARE_FILTER_PREDICATE_TAG = 'flare.filter_predicate';

    /**
     * Configures the options for this type.
     */
    public function configureOptions(OptionsResolver $resolver): void;

    /**
     * Builds the filter's conditions.
     *
     * @param array<string, mixed> $options
     */
    public function buildConditions(FilterConditionsBuilder $builder, array $options): void;
}
