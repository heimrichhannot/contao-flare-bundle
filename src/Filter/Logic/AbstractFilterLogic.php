<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Logic;

use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractFilterLogic implements FilterLogicInterface
{
    public function configureOptions(OptionsResolver $resolver): void {}

    abstract public function buildConditions(FilterConditionsBuilder $builder, array $options): void;
}
