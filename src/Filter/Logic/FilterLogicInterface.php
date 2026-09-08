<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Logic;

use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AutoconfigureTag(self::FLARE_FILTER_LOGIC_TAG)]
interface FilterLogicInterface
{
    public const FLARE_FILTER_LOGIC_TAG = 'flare.filter_logic';

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
