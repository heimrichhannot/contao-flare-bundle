<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class FilterContext
{
    public function __construct(
        private ListModel           $listModel,
        private FilterModel         $filterModel,
        private FilterElementConfig $filterElementConfig,
        private string              $filterElementAlias,
        private string              $table,
    ) {}

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getFilterModel(): FilterModel
    {
        return $this->filterModel;
    }

    public function getConfig(): FilterElementConfig
    {
        return $this->filterElementConfig;
    }

    public function getFilterAlias(): string
    {
        return $this->filterElementAlias;
    }

    public function getTable(): string
    {
        return $this->table;
    }
}