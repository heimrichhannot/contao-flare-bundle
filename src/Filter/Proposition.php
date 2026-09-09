<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Filter\Predicate\PredicateInterface;

final readonly class Proposition
{
    public function __construct(
        public PredicateInterface $predicate,
        public string             $predicateClass,
        public string             $targetAlias,
        public array              $options,
    ) {}
}
