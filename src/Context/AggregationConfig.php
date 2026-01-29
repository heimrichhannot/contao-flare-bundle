<?php

namespace HeimrichHannot\FlareBundle\Context;

class AggregationConfig implements ContextConfigInterface
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