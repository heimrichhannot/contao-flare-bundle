<?php

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Filter\FormulaBuilder;
use HeimrichHannot\FlareBundle\Registry\FilterPredicateRegistry;

final readonly class FormulaBuilderFactory
{
    public function __construct(
        private FilterPredicateRegistry $filterPredicateRegistry,
    ) {}

    public function create(string $defaultTargetAlias = null): FormulaBuilder
    {
        return new FormulaBuilder(
            filterPredicateRegistry: $this->filterPredicateRegistry,
            defaultTargetAlias: $defaultTargetAlias,
        );
    }
}
