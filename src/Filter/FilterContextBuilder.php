<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Filter\Predicate\PredicateInterface;

/**
 * What {@see Element\FilterElementInterface::buildContext()} receives: the scope, and the only way
 * an element applies predicates.
 */
class FilterContextBuilder
{
    public function __construct(
        public readonly FilterScope              $scope,
        private readonly FormulaBuilderInterface $formulaBuilder,
    ) {}

    /**
     * @param class-string<PredicateInterface> $predicateClass
     * @param array<string, mixed> $options
     */
    public function addPredicate(string $predicateClass, array $options = [], ?string $targetAlias = null): self
    {
        $this->formulaBuilder->add($predicateClass, $options, $targetAlias);

        return $this;
    }

    public function abort(): never
    {
        throw new AbortFilteringException();
    }

    public function build(): FilterContext
    {
        return new FilterContext(
            scope: $this->scope,
            formula: $this->formulaBuilder->build(),
        );
    }
}
