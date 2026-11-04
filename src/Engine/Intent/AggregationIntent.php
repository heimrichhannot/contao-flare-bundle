<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Intent;

use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

class AggregationIntent implements IntentInterface
{
    public static function getIntentType(): string
    {
        return 'aggregation';
    }

    /**
     * @param array<string, ?ValueInterface> $filterValues Filter values keyed by filter alias.
     */
    public function __construct(
        private array $filterValues = [],
    ) {}

    /**
     * @return array<string, ?ValueInterface>
     */
    public function getFilterValues(): array
    {
        return $this->filterValues;
    }

    /**
     * @param array<string, ?ValueInterface> $values
     */
    public function withFilterValues(array $values): self
    {
        $clone = clone $this;
        $clone->filterValues = $values;
        return $clone;
    }
}
