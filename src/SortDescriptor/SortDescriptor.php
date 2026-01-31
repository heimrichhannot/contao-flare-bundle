<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\SortDescriptor;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;

final class SortDescriptor
{
    public function __construct(
        private array $orders,
        private bool $ignoreCase = false,
    )  {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function by(string $property, string $direction = Order::ASC): self
    {
        return new self([Order::of($property, $direction)]);
    }

    public function next(string $property, string $direction = Order::ASC): self
    {
        $this->orders[] = Order::of($property, $direction);
        return $this;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function isEmpty(): bool
    {
        return \count($this->orders) < 1;
    }

    public function isIgnoreCase(): bool
    {
        return $this->ignoreCase;
    }

    public function setIgnoreCase(bool $ignoreCase): self
    {
        $this->ignoreCase = $ignoreCase;
        return $this;
    }

    public function toSql(?callable $quoteColumn = null): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $parts = \array_map(
            function (Order $o) use ($quoteColumn): string {
                if ($quoteColumn) {
                    $col = $quoteColumn($o->getColumn());
                } else {
                    $col = $o->getColumn();
                }

                if ($this->ignoreCase) {
                    $col = "LOWER({$col})";
                }

                return "{$col} {$o->getDirection()}";
            },
            $this->orders
        );

        return \implode(', ', $parts);
    }
}