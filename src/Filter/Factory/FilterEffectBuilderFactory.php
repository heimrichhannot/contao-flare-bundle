<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilder;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;

final readonly class FilterEffectBuilderFactory
{
    public function __construct(
        private FormulaBuilderFactory $formulaBuilderFactory,
        private FilterElementRegistry $filterElementRegistry,
    ) {}

    public function create(ElementContext $context): FilterEffectBuilder
    {
        return new FilterEffectBuilder(
            formulaBuilder: $this->formulaBuilderFactory->create($this->resolveTargetAlias($context)),
        );
    }

    /**
     * v0.1 targeting stopgap, to be redesigned before the v0.2 release: do not extend.
     */
    private function resolveTargetAlias(ElementContext $context): string
    {
        $filter = $context->filter;
        $isTargeted = $this->filterElementRegistry->getAttribute($filter->element->type)?->isTargeted;

        if ($isTargeted || $filter->targetingForced) {
            return $filter->targetAlias ?: TableAliasRegistry::ALIAS_MAIN;
        }

        return TableAliasRegistry::ALIAS_MAIN;
    }
}
