<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Filter\Collector\FilterCollectors;
use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;

class ListDefinitionBuilder
{
    private ListDataSource $dataSource;

    public function __construct(
        private readonly FilterCollectors $collectors,
    ) {}

    public function getDataSource(): ListDataSource
    {
        return $this->dataSource;
    }

    public function setDataSource(ListDataSource $dataSource): static
    {
        $this->dataSource = $dataSource;
        return $this;
    }

    private function autoCollectFilters(): ?FilterDefinitionCollection
    {
        if (!$collector = $this->collectors->match($this->dataSource)) {
            return null;
        }

        $collection = $collector->collect($this->dataSource);

        // $event = $this->eventDispatcher->dispatch(
        //     new ListFiltersCollectedEvent(
        //         filters: $collection,
        //         listModel: $listModel,
        //     )
        // ); todo(@ericges): dispatch this event somewhere?

        return $collection;
    }

    public function build(): ListDefinition
    {
        if (!isset($this->dataSource)) {
            throw new \RuntimeException('Dat source not set.');
        }

        $listDefinition = new ListDefinition(
            type: $this->dataSource->getListType(),
            dc: $this->dataSource->getListTable(),
            dataSource: $this->dataSource,
            filters: $this->autoCollectFilters(),
        );

        $listDefinition->setProperties($this->dataSource->getListData());

        return $listDefinition;
    }
}