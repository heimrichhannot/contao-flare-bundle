<?php

namespace HeimrichHannot\FlareBundle\Filter\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\List\ListSpec;

final readonly class FilterContextBuilderFactory
{
    public function __construct(
        private FilterOptionsResolver $filterOptionsResolver,
        private FormulaBuilderFactory $formulaBuilderFactory,
    ) {}

    public function create(ListSpec $list, Filter $filter, ContextInterface $engineContext): FilterContextBuilder
    {
        $config = $this->filterOptionsResolver->resolve($filter);

        return new FilterContextBuilder(
            formulaBuilderFactory: $this->formulaBuilderFactory,
            list: $list,
            filter: $filter,
            engineContext: $engineContext,
            config: $config,
        );
    }
}
