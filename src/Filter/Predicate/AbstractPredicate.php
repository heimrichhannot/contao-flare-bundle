<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Predicate;

use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractPredicate implements PredicateInterface
{
    public function configureOptions(OptionsResolver $resolver): void {}

    abstract public function buildConditions(FilterConditionsBuilder $builder, array $options): void;
}
