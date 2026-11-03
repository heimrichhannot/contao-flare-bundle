<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;

final readonly class FilterContextBuilderFactory
{
    public function __construct(
        private FormulaBuilderFactory $formulaBuilderFactory,
        private FilterElementRegistry $filterElementRegistry,
    ) {}

    public function create(ElementScope $scope): FilterContextBuilder
    {
        return new FilterContextBuilder(
            formulaBuilder: $this->formulaBuilderFactory->create($this->resolveTargetAlias($scope)),
        );
    }

    /**
     * v0.1 targeting stopgap, to be redesigned before the v0.2 release: do not extend.
     */
    private function resolveTargetAlias(ElementScope $scope): string
    {
        $filter = $scope->filter;
        $isTargeted = $this->filterElementRegistry->getAttribute($filter->element->type)?->isTargeted;

        if ($isTargeted || $filter->targetingForced) {
            return $filter->targetAlias ?: TableAliasRegistry::ALIAS_MAIN;
        }

        return TableAliasRegistry::ALIAS_MAIN;
    }
}
