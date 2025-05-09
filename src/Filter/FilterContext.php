<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

class FilterContext
{
    public function __construct(
        private readonly ListModel           $listModel,
        private readonly FilterModel         $filterModel,
        private readonly FilterElementConfig $filterElementConfig,
        private readonly string              $filterElementAlias,
        private readonly string              $table,
        private mixed                        $submittedData = null
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

    public function getSubmittedData(): mixed
    {
        return $this->submittedData;
    }

    public function setSubmittedData(mixed $submittedData): void
    {
        $this->submittedData = $submittedData;
    }
}