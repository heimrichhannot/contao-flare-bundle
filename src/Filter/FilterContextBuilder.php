<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;
use HeimrichHannot\FlareBundle\Util\Str;

class FilterContextBuilder
{
    private ContentContext $contentContext;
    private object $filterElement;
    private ?string $filterElementAlias = null;
    private ?FilterElementDescriptor $filterElementDescriptor = null;
    private ?FilterModel $filterModel = null;
    private array $filterModelProperties = [];
    private ListModel $listModel;

    public function __construct() {}

    public function setContentContext(ContentContext $contentContext): static
    {
        $this->contentContext = $contentContext;
        return $this;
    }

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

    public function setFilterElementDescriptor(?FilterElementDescriptor $descriptor): static
    {
        $this->filterElementDescriptor = $descriptor;
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

        $alias = $this->filterElementAlias;

        if (!$alias) {
            $alias = $this->filterElementDescriptor?->getAttributes()['alias'] ?? null;
        }

        if (!$alias) {
            $alias = '_auto_' . Str::random(8, Str::CHARS_ALPHA_LOWER);
        }

        $config = $this->filterElementDescriptor ?? new FilterElementDescriptor($this->filterElement, ['alias' => $alias]);

        return new FilterContext(
            contentContext: $this->contentContext,
            listModel: $this->listModel,
            filterModel: $filterModel,
            filterElementDescriptor: $config,
            filterElementAlias: $alias,
            table: $table,
        );
    }
}