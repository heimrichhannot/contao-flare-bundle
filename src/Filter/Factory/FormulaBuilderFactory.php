<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Filter\FormulaBuilder;
use HeimrichHannot\FlareBundle\Registry\FilterPredicateRegistry;

/**
 * @internal
 */
final readonly class FormulaBuilderFactory
{
    public function __construct(
        private FilterPredicateRegistry $filterPredicateRegistry,
    ) {}

    public function create(string $defaultTargetAlias): FormulaBuilder
    {
        return new FormulaBuilder(
            filterPredicateRegistry: $this->filterPredicateRegistry,
            defaultTargetAlias: $defaultTargetAlias,
        );
    }
}
