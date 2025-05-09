<?php

namespace HeimrichHannot\FlareBundle\Filter\Builder;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterElementConfig;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\Str;

class FilterContextBuilder
{
    private object $filterElement;
    private ?string $filterElementAlias = null;
    private ?FilterModel $filterModel = null;
    private array $filterModelProperties = [];
    private ListModel $listModel;

    public function __construct() {}

    public function setFilterElement(object $filterElement): static
    {
        $this->filterElement = $filterElement;
        return $this;
    }

    public function setFilterElementAlias(?string $alias): static
    {
        $this->filterElementAlias = $alias;
        return $this;
    }

    public function setFilterModel(FilterModel $filterModel): static
    {
        $this->filterModel = $filterModel;
        return $this;
    }

    public function setFilterModelProperties(array $filterModelProperties): static
    {
        $this->filterModelProperties = $filterModelProperties;
        return $this;
    }

    public function setListModel(ListModel $listModel): static
    {
        $this->listModel = $listModel;
        return $this;
    }

    public function build(): ?FilterContext
    {
        if (!isset($this->listModel) || !$table = $this->listModel->dc) {
            return null;
        }

        $filterModel = $this->filterModel ?? new FilterModel();
        foreach ($this->filterModelProperties as $prop => $value) {
            $filterModel->{$prop} = $value;
        }

        $alias = $this->filterElementAlias ?: ('_auto_' . Str::random(8, Str::CHARS_ALPHA_LOWER));
        $config = new FilterElementConfig($this->filterElement, ['alias' => $alias]);

        return new FilterContext(
            listModel: $this->listModel,
            filterModel: $filterModel,
            filterElementConfig: $config,
            filterElementAlias: $alias,
            table: $table,
        );
    }
}