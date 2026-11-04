<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Filter\Predicate\PredicateInterface;

/**
 * What {@see Element\FilterElementInterface::buildEffect()} receives: the only way an element
 * contributes to its {@see FilterEffect}.
 *
 * @api
 */
interface FilterEffectBuilderInterface
{
    /**
     * @param class-string<PredicateInterface> $predicateClass
     * @param array<string, mixed> $options
     */
    public function addPredicate(string $predicateClass, array $options = [], ?string $targetAlias = null): static;

    /**
     * @throws AbortFilteringException
     */
    public function abort(): never;
}
