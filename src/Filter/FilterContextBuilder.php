<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Util\Str;

class FilterContextBuilder
{
    private ContentContext $contentContext;
    private ?FilterDefinition $filterDefinition = null;
    private object $filterElement;
    private ?string $filterElementType = null;
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

    public function setFilterDefinition(?FilterDefinition $filterDefinition): static
    {
        $this->filterDefinition = $filterDefinition;
        return $this;
    }

    public function getFilterDefinition(): ?FilterDefinition
    {
        return $this->filterDefinition;
    }

    public function setFilterElement(object $filterElement): static
    {
        $this->filterElement = $filterElement;
        return $this;
    }

    public function setFilterElementType(?string $alias): static
    {
        $this->filterElementType = $alias;
        return $this;
    }

    public function setFilterElementDescriptor(?FilterElementDescriptor $descriptor): static
    {
        $this->filterElementDescriptor = $descriptor;
        return $this;
    }

    /**
     * @param FilterModel $filterModel
     * @return $this
     * @deprecated Use {@see setFilterDefinition()} instead.
     */
    public function setFilterModel(FilterModel $filterModel): static
    {
        // todo(@ericges): remove all usages of this method
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

        $type = $this->filterElementType;

        if (!$type) {
            $type = $this->filterElementDescriptor?->getAttributes()['type'] ?? null;
        }

        if (!$type) {
            $type = '_auto_' . Str::random(8, Str::CHARS_ALPHA_LOWER);
        }

        $config = $this->filterElementDescriptor ?? new FilterElementDescriptor($this->filterElement, ['type' => $type]);

        return new FilterContext(
            contentContext: $this->contentContext,
            listModel: $this->listModel,
            filterModel: $filterModel,
            filterElementDescriptor: $config,
            filterElementType: $type,
            table: $table,
        );
    }
}