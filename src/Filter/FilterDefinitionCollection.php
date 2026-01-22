<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Collection\AbstractCollection;

/**
 * @method \Traversable<int, FilterDefinition> getIterator
 */
class FilterDefinitionCollection extends AbstractCollection
{
    private array $mapAliasFilters = [];

    protected function getItemType(): string
    {
        return FilterDefinition::class;
    }

    /**
     * @param FilterDefinition $item
     */
    public function add(mixed $item): static
    {
        parent::add($item);

        $this->mapAliasFilters[$item->type][] = $item;

        return $this;
    }

    /**
     * @param FilterDefinition $item
     */
    public function remove(mixed $item): bool
    {
        $yes = parent::remove($item);

        if (!$yes || !isset($this->mapAliasFilters[$item->type])) {
            return false;
        }

        $filtered = \array_filter(
            $this->mapAliasFilters[$item->type],
            static fn (FilterDefinition $filter): bool => $filter !== $item
        );

        $this->mapAliasFilters[$item->type] = \array_values($filtered);

        return true;
    }

    public function hasFilterOfAlias(string $alias): bool
    {
        return isset($this->mapAliasFilters[$alias]) && \count($this->mapAliasFilters[$alias]) > 0;
    }

    public function hash(): string
    {
        return \sha1(\serialize(\array_map(
            static fn (FilterDefinition $filter): string => $filter->hash(),
            $this->items
        )));
    }
}