<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Specification\DataSource;

interface FilterDataSourceInterface
{
    public function getFilterType(): string;

    public function isFilterIntrinsic(): bool;

    public function getFilterTargetAlias(): string;

    public function getFilterFormName(): string;

    public function getFilterData(): array;

    public function getFilterProperty(string $name): mixed;
}