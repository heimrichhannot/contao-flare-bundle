<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Sort;

use HeimrichHannot\FlareBundle\Exception\FlareException;

final class SortOrderSequence
{
    /**
     * @param SortOrder[] $items
     * @throws FlareException
     */
    public function __construct(
        private array $items = [],
    ) {
        $this->items = \array_values($this->items);
        $this->assertUnique($this->items);
    }

    /**
     * @return SortOrder[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return \count($this->items) < 1;
    }

    public function append(SortOrder $sort): self
    {
        $this->assertUnique([...$this->items, $sort]);
        $this->items[] = $sort;
        return $this;
    }

    /**
     * @param list<SortOrder> $items
     * @throws FlareException
     */
    private function assertUnique(array $items): void
    {
        $seen = [];
        foreach ($items as $order) {
            $key = $order->key();
            if (isset($seen[$key])) {
                throw new FlareException("Duplicate sort key in sequence: {$key}");
            }
            $seen[$key] = true;
        }
    }
}