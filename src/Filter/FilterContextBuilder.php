<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Filter\Predicate\PredicateInterface;

/**
 * Collects what an element contributes to its {@see FilterContext}. Elements only see the
 * {@see FilterContextBuilderInterface}; {@see build()} is the framework's.
 *
 * @internal
 */
final class FilterContextBuilder implements FilterContextBuilderInterface
{
    public function __construct(
        private readonly FormulaBuilderInterface $formulaBuilder,
    ) {}

    /**
     * @param class-string<PredicateInterface> $predicateClass
     * @param array<string, mixed> $options
     */
    public function addPredicate(string $predicateClass, array $options = [], ?string $targetAlias = null): static
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
        return new FilterContext(formula: $this->formulaBuilder->build());
    }
}
