<?php

namespace HeimrichHannot\FlareBundle\Engine\Context;

class AggregationContext implements ContextInterface
{
    public static function getContextType(): string
    {
        return 'aggregation';
    }

    public function __construct(
        private array $filterValues = [],
    ) {}

    public function getFilterValues(): array
    {
        return $this->filterValues;
    }

    public function withFilterValues(array $values): self
    {
        $clone = clone $this;
        $clone->filterValues = $values;
        return $clone;
    }
}