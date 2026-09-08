<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Filter\Factory\FormulaBuilderFactory;
use HeimrichHannot\FlareBundle\List\ListSpec;

class FilterContextBuilder
{
    private array $predicates = [];

    public function __construct(
        public readonly FormulaBuilderFactory $formulaBuilderFactory,
        public readonly ListSpec $list,
        public readonly Filter $filter,
        public readonly ContextInterface $engineContext,
        public readonly array $config,
    ) {}

    public function addPredicate(string $type, array $options = [], ?string $targetAlias = null): self
    {
        $this->predicates[] = [$type, $options, $targetAlias];

        return $this;
    }

    public function setPredicates(array $predicates): self
    {
        $this->predicates = $predicates;
        return $this;
    }

    public function build(): FilterContext
    {
        $formulaBuilder = $this->formulaBuilderFactory->create($this->filter->targetAlias);

        foreach ($this->predicates as [$type, $options, $targetAlias]) {
            $formulaBuilder->add($type, $options, $targetAlias);
        }

        $formula = $formulaBuilder->build();

        return new FilterContext(
            list: $this->list,
            filter: $this->filter,
            config: $this->config,
            formula: $formula,
            engineContext: $this->engineContext,
        );
    }

    public function abort(): never
    {
        throw new AbortFilteringException();
    }
}
