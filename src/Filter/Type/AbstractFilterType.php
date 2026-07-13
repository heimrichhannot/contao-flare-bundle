<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractFilterType implements FilterTypeInterface
{
    public function configureOptions(OptionsResolver $resolver): void {}

    abstract public function buildQuery(FilterQueryBuilder $builder, array $options): void;
}
