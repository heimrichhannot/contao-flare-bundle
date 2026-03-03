<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Specification\DataSource;

interface ListDataSourceInterface
{
    public function getListType(): string;

    public function getListTable(): string;

    public function getListData(): array;

    public function getListProperty(string $name): mixed;
}