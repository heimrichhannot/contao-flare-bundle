<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Filter\Predicate\PredicateInterface;

interface FormulaBuilderInterface
{
    /**
     * @param class-string<PredicateInterface> $predicateClass
     * @param array<string, mixed> $options
     */
    public function add(string $predicateClass, array $options = [], ?string $targetAlias = null): static;

    public function abort(): never;

    public function build(): Formula;
}
