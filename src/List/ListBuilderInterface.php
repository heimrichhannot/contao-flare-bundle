<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\Type\ListTypeInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;

interface ListBuilderInterface
{
    public function getType(): ListTypeInterface|string;

    public function getTypeAlias(): ?string;

    public function getTypeService(): ?object;

    public function getDc(): string;

    public function getModel(): ?ListModel;

    public function getSource(): ?string;

    public function set(string $key, mixed $value): self;

    public function addFilter(Filter $filter, ?string $key = null): self;

    public function removeFilter(string $key): self;

    public function hasFilterOfType(string $elementType): bool;

    public function getFilters(): array;

    public function getFilter(string $key): ?Filter;

    public function build(): ListSpec;
}
