<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\Manager\FilterDefinitionManager;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;

class ListDefinitionBuilder
{
    private FilterDefinitionCollection $filters;
    private ListModel $listModel;
    private string $filterFormName;

    public function __construct(
        private readonly FilterDefinitionManager $filterDefinitionManager,
    ) {
        $this->filters = new FilterDefinitionCollection();
    }

    public function getFilters(): FilterDefinitionCollection
    {
        return $this->filters;
    }

    public function setFilters(FilterDefinitionCollection $filters): static
    {
        $this->filters = $filters;
        return $this;
    }

    public function autoCollectFilters(): static
    {
        $this->filters = $this->filterDefinitionManager->collectListModelFilterDefinitions($this->listModel);
        return $this;
    }

    public function addFilter(FilterDefinition $filterDefinition): static
    {
        $this->filters->add($filterDefinition);
        return $this;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function setListModel(ListModel $listModel): static
    {
        $this->listModel = $listModel;
        return $this;
    }

    public function getFilterFormName(): ?string
    {
        return $this->filterFormName;
    }

    public function setFilterFormName(?string $filterFormName): static
    {
        $this->filterFormName = $filterFormName;
        return $this;
    }

    public function build(): ListDefinition
    {
        if (!isset($this->listModel)) {
            throw new \RuntimeException('List model not set.');
        }

        $listDefinition = new ListDefinition(
            type: $this->listModel->type,
            dc: $this->listModel->dc,
            sourceListModel: $this->listModel,
            filterFormName: $this->filterFormName ?? 'fl' . $this->listModel->id,
        );

        $listDefinition->setProperties($this->listModel->row());

        return $listDefinition;
    }
}