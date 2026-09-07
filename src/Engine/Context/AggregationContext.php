<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context;

use HeimrichHannot\FlareBundle\Filter\FilterData;

class AggregationContext implements ContextInterface
{
    public static function getContextType(): string
    {
        return 'aggregation';
    }

    /**
     * @param array<string|int, FilterData> $filterValues
     */
    public function __construct(
        private array $filterValues = [],
    ) {}

    /**
     * @return array<string|int, FilterData>
     */
    public function getFilterValues(): array
    {
        return $this->filterValues;
    }

    /**
     * @param array<string|int, FilterData> $values
     */
    public function withFilterValues(array $values): self
    {
        $clone = clone $this;
        $clone->filterValues = $values;
        return $clone;
    }
}