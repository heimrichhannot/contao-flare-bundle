<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;

interface ListSpecBuilderInterface
{
    public function getDriver(): ListDriverInterface;

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
