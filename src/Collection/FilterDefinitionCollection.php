<?php

namespace HeimrichHannot\FlareBundle\Collection;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;

class FilterDefinitionCollection implements \IteratorAggregate, \Countable, \Serializable
{
    private array $items = [];

    public function __construct(
        ?array $items = null,
    ) {
        $this->initItems($items ?? []);
    }

    private function initItems(array $items): void
    {
        if (!$items) {
            return;
        }

        if (\array_is_list($items)) {
            $this->add(...$items);
            return;
        }

        foreach ($items as $key => $filter) {
            $this->items[(string) $key] = $filter;
        }
    }

    public function get(string $key): ?FilterDefinition
    {
        return $this->items[$key] ?? null;
    }

    /**
     * @return array<int, FilterDefinition>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->items);
    }

    public function hasType(string $type): bool
    {
        return \array_reduce(
            $this->items,
            static fn (bool $carry, FilterDefinition $filter): bool => $carry || $filter->getType() === $type,
            false
        );
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function add(FilterDefinition ...$item): static
    {
        foreach ($item as $filter) {
            do {
                $randomKey = '_generated_' . \bin2hex(\random_bytes(4));
            } while (\array_key_exists($randomKey, $this->items));

            $this->items[$randomKey] = $filter;
        }

        return $this;
    }

    public function set(string $key, FilterDefinition $filter): void
    {
        $this->items[$key] = $filter;
    }

    /**
     * @param FilterDefinition|string $item The item to remove or its key.
     */
    public function remove(FilterDefinition|string $item): bool
    {
        if (\is_string($item)) {
            if (!\array_key_exists($item, $this->items)) {
                return false;
            }
            unset($this->items[$item]);
            return true;
        }

        $beforeCount = \count($this->items);

        $filtered = \array_filter(
            $this->items,
            static fn (FilterDefinition $filter): bool => $filter !== $item
        );

        $this->items = $filtered;

        return \count($this->items) < $beforeCount;
    }

    /**
     * Get the values of the collection as an array.
     *
     * @return array<int, FilterDefinition> The values of the collection.
     */
    public function values(): array
    {
        return \array_values($this->items);
    }

    /**
     * Get the number of items in the collection.
     *
     * @return int The count of items.
     */
    public function count(): int
    {
        return \count($this->items);
    }

    /**
     * Retrieve an iterator for the items.
     *
     * @return \Traversable<string, FilterDefinition> Iterator for the collection items.
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }


    /**
     * Serialize the collection.
     *
     * @return string Serialized representation of the collection.
     */
    public function serialize(): string
    {
        return \serialize($this->items);
    }

    /**
     * Unserialize data into the collection.
     *
     * @param string $data The serialized data.
     * @throws \UnexpectedValueException if the data is not an array of the expected type.
     */
    public function unserialize(string $data): void
    {
        $unserialized = StringUtil::deserialize($data);

        if (!is_array($unserialized)) {
            throw new \UnexpectedValueException("Invalid data: expected an array.");
        }

        $this->items = [];
        $this->initItems($unserialized);
    }


    /**
     * Magic method for serialization (PHP 8.2 preferred).
     *
     * @return array<int, FilterDefinition> Data to serialize.
     */
    public function __serialize(): array
    {
        return $this->items;
    }

    /**
     * Magic method for unserialization (PHP 8.2 preferred).
     *
     * @param array<int, FilterDefinition> $data Data array to restore into the object.
     * @throws \UnexpectedValueException if any item is of an incorrect type.
     */
    public function __unserialize(array $data): void
    {
        $this->items = [];
        $this->initItems($data);
    }

    public function __clone(): void
    {
        $this->items = \array_map(static fn (FilterDefinition $item): FilterDefinition => clone $item, $this->items);
    }

    public function hash(): string
    {
        return \sha1(\serialize(\array_map(
            static fn (FilterDefinition $filter): string => $filter->hash(),
            $this->items
        )));
    }
}