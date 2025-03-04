<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Collection;

use IteratorAggregate;
use Countable;
use Serializable;
use ArrayIterator;
use Traversable;
use TypeError;
use UnexpectedValueException;

/**
 * Abstract generic collection class.
 *
 * @template T
 * @implements IteratorAggregate<int, T>
 * @implements Countable
 * @implements Serializable
 */
abstract class Collection implements IteratorAggregate, Countable, Serializable
{
    /**
     * @var array<int, T> Stored items in the collection
     */
    protected array $items = [];

    /**
     * Returns the expected item class or type for this collection.
     * Child classes must specify the allowed item type (e.g., a class name).
     *
     * @return class-string<T> The expected type of items.
     */
    abstract protected function getItemType(): string;

    /**
     * Constructor optionally accepts an initial array of items (all must be of correct type).
     *
     * @param array<int, T> $items Initial items to include in the collection.
     * @throws TypeError if any of the initial items are of the wrong type.
     */
    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->add($item);  // reuse the add method to enforce type checks
        }
    }

    /**
     * Add an item to the collection.
     *
     * @param T $item The item to add (must match the collection's item type).
     * @return static Allows method chaining.
     * @throws TypeError if the item is not of the expected type.
     */
    public function add(mixed $item): static
    {
        $expectedType = $this->getItemType();

        // Enforce type safety: check if item matches the expected type.
        if (!$this->isItemOfType($item, $expectedType)) {
            $typeName = \is_object($item) ? \get_class($item) : \gettype($item);
            throw new TypeError("Expected item of type {$expectedType}, got {$typeName}.");
        }

        $this->items[] = $item;

        return $this;
    }

    /**
     * Remove an item from the collection.
     * If the same object/value exists multiple times, only the first occurrence is removed.
     *
     * @param T $item The item to remove.
     * @return bool True if an item was removed, false if not found.
     */
    public function remove(mixed $item): bool
    {
        foreach ($this->items as $index => $value) {
            if ($value === $item) {
                \array_splice($this->items, $index, 1);
                return true;
            }
        }
        return false;
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
     * @return Traversable<int, T> Iterator for the collection items.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
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
     * @throws UnexpectedValueException if the data is not an array of the expected type.
     */
    public function unserialize(string $data): void
    {
        $unserialized = \unserialize($data);

        if (!is_array($unserialized)) {
            throw new UnexpectedValueException("Invalid data: expected an array.");
        }

        $this->items = [];
        foreach ($unserialized as $item) {
            $this->add($item);
        }
    }

    /**
     * Magic method for serialization (PHP 8.2 preferred).
     *
     * @return array<int, T> Data to serialize.
     */
    public function __serialize(): array
    {
        return $this->items;
    }

    /**
     * Magic method for unserialization (PHP 8.2 preferred).
     *
     * @param array<int, T> $data Data array to restore into the object.
     * @throws UnexpectedValueException if any item is of incorrect type.
     */
    public function __unserialize(array $data): void
    {
        $this->items = [];
        foreach ($data as $item) {
            $this->add($item);
        }
    }

    /**
     * Helper method to check if a value is of a given expected type.
     *
     * @param mixed  $item The item to check.
     * @param string $expectedType The expected type (class name or primitive type name).
     * @return bool True if $item matches the type, false otherwise.
     */
    protected function isItemOfType(mixed $item, string $expectedType): bool
    {
        if (\class_exists($expectedType) || \interface_exists($expectedType)) {
            return $item instanceof $expectedType;
        }

        return match ($expectedType) {
            'int', 'integer'   => is_int($item),
            'bool', 'boolean'  => is_bool($item),
            'string'           => is_string($item),
            'float', 'double'  => is_float($item),
            'array'            => is_array($item),
            'object'           => is_object($item),
            'callable'         => is_callable($item),
            'resource'         => is_resource($item),
            'null'             => $item === null,
            'mixed'            => true,
            default            => false
        };
    }
}
