<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class FilterContext
{
    public function __construct(
        private FilterModel $filterModel,
        private ListModel   $listModel,
        private string      $table,
    ) {}

    public function getFilterModel(): FilterModel
    {
        return $this->filterModel;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getTable(): string
    {
        return $this->table;
    }

}