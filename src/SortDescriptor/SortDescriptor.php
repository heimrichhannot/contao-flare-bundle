<?php declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\SortDescriptor;

use HeimrichHannot\FlareBundle\Exception\FlareException;

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

    /**
     * Creates a sort descriptor from a map of column names and directions.
     *
     * Example:
     * ```
     * $sd = SortDescriptor::fromMap([
     *     'name' => 'asc',
     *     'age' => 'desc'
     * ]);
     * ```
     *
     * @param array $map  An array of column names as keys and directions as their values
     *                      (`ASC` or `DESC`, case-insensitive).
     * @return self  A new SortDescriptor instance.
     * @throws FlareException  If the map is not in the expected format.
     */
    public static function fromMap(array $map): self
    {
        $orders = [];
        foreach ($map as $column => $direction)
        {
            if (!\is_string($column) || !\is_string($direction)) {
                throw new FlareException('Invalid sort settings format. Expected array with string keys and values.', 500);
            }

            $orders[] = Order::of($column, $direction);
        }

        return new self($orders);
    }

    /**
     * @throws FlareException If the settings are not in the expected format.
     */
    public static function fromSettings(array $settings): self
    {
        $orders = [];
        $columns = [];
        foreach ($settings as $item)
        {
            if (!\is_array($item) || \count($item) !== 2) {
                throw new FlareException('Invalid sort settings format. Expected array of arrays with two elements.', 500);
            }

            if (!isset($item['column'], $item['direction'])) {
                throw new FlareException('Invalid sort settings format. Expected array with "column" and "direction" keys.', 500);
            }

            ['column' => $column, 'direction' => $direction] = $item;

            if (\is_string($column) && \is_string($direction))
            {
                $column = 'main.' . \trim($column);

                if (isset($columns[$column])) {
                    throw new FlareException('Duplicate column name found in sort settings: ' . $column, 500);
                }

                $orders[] = Order::of($column, $direction);
                $columns[$column] = true;
            }
        }

        return new self(\array_values($orders));
    }

    public static function by(string $property, string $direction = Order::ASC): self
    {
        return new self([Order::of($property, $direction)]);
    }

    public function and(string $property, string $direction = Order::ASC): self
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

    public function toSql(callable $quoteColumn): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $ignoreCase = $this->ignoreCase;

        $parts = \array_map(
            static function (Order $o) use ($quoteColumn, $ignoreCase): string {
                $col = $quoteColumn($o->getColumn());

                if ($ignoreCase) {
                    $col = "LOWER($col)";
                }

                return "$col {$o->getDirection()}";
            },
            $this->orders
        );

        return implode(', ', $parts);
    }
}