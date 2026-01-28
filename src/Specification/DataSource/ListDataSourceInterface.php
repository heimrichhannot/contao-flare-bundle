<?php

namespace HeimrichHannot\FlareBundle\Specification\DataSource;

interface ListDataSourceInterface
{
    public function getListType(): string;

    public function getListTable(): string;

    public function getListData(): array;

    public function __get($name); // cannot declare types here, because of compatibility with Contao\Model

    public function __set($name, $value);
}