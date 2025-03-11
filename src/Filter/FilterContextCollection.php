<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Collection\Collection;

/**
 * A type-safe collection specifically for FilterContext objects.
 *
 * @extends Collection<FilterContext>
 */
class FilterContextCollection extends Collection
{
    protected string $table;

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    /** {@inheritDoc} */
    protected function getItemType(): string
    {
        return FilterContext::class;
    }
}