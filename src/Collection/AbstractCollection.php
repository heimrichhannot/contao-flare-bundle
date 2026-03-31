<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Collection;

/**
 * @template T The type of items in the collection.
 */
abstract class AbstractCollection implements \IteratorAggregate, \Countable, \Serializable
{
    protected array $items = [];

    /**
     * Get an item from the collection by key.
     *
     * @param string $key
     * @return T
     */
    abstract public function get(string $key): mixed;

    /**
     * Determine if an item exists in the collection by key.
     *
     * @param string $key
     * @return bool
     */
    abstract public function has(string $key): bool;

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return \count($this->items) < 1;
    }

    /**
     * Get all items in the collection.
     *
     * @return array<int, T>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the values of the collection as an array.
     *
     * @return array<int, T> The values of the collection.
     */
    public function values(): array
    {
        return \array_values($this->items);
    }

    /**
     * Retrieve an iterator for the items.
     *
     * @return \Traversable<string, T> Iterator for the collection items.
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
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
}