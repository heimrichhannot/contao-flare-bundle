<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;

interface ListCompoundBuilderInterface
{
    public function getDriver(): ListDriverInterface|string;

    public function getModel(): ?ListModel;

    public function getSource(): ?string;

    public function set(string $key, mixed $value): self;

    public function addFilter(FilterCompound $filter, ?string $key = null): self;

    public function removeFilter(string $key): self;

    /**
     * @param class-string<FilterElementInterface> $class
     */
    public function hasFilterInstance(string $class): bool;

    public function getFilters(): array;

    public function getFilter(string $key): ?FilterCompound;

    public function build(): ListCompound;
}
