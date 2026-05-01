<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Collection;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;

/**
 * @method array<int|string, ConfiguredFilter> all() Get the items of the collection.
 * @method array<int|string, ConfiguredFilter> values() Get the values of the collection.
 * @method \Traversable<string, ConfiguredFilter> getIterator() Iterator for the collection items.
 */
class ConfiguredFilterCollection extends AbstractCollection
{
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

    public function get(string $key): ?ConfiguredFilter
    {
        return $this->items[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->items);
    }

    public function hasType(string $type): bool
    {
        return \array_reduce(
            $this->items,
            static fn (bool $carry, ConfiguredFilter $filter): bool => $carry || $filter->getElementType() === $type,
            false
        );
    }

    public function add(ConfiguredFilter ...$item): static
    {
        foreach ($item as $filter) {
            do {
                $randomKey = '_generated_' . \bin2hex(\random_bytes(4));
            } while (\array_key_exists($randomKey, $this->items));

            $this->items[$randomKey] = $filter;
        }

        return $this;
    }

    public function set(string $key, ConfiguredFilter $filter): void
    {
        $this->items[$key] = $filter;
    }

    /**
     * @param ConfiguredFilter|string $item The item to remove or its key.
     */
    public function remove(ConfiguredFilter|string $item): bool
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
            static fn (ConfiguredFilter $filter): bool => $filter !== $item
        );

        $this->items = $filtered;

        return \count($this->items) < $beforeCount;
    }

    public function serialize(): string
    {
        return \serialize($this->items);
    }

    public function unserialize(string $data): void
    {
        $unserialized = StringUtil::deserialize($data);

        if (!\is_array($unserialized)) {
            throw new \UnexpectedValueException('Invalid data: expected an array.');
        }

        $this->items = [];
        $this->initItems($unserialized);
    }

    public function __serialize(): array
    {
        return $this->items;
    }

    public function __unserialize(array $data): void
    {
        $this->items = [];
        $this->initItems($data);
    }

    public function __clone(): void
    {
        $this->items = \array_map(static fn (ConfiguredFilter $item): ConfiguredFilter => clone $item, $this->items);
    }

    public function hash(): string
    {
        return \sha1(\serialize(\array_map(
            static fn (ConfiguredFilter $filter): string => $filter->hash(),
            $this->items
        )));
    }
}
