<?php

namespace HeimrichHannot\FlareBundle\Specification\Factory;

use HeimrichHannot\FlareBundle\Event\ListFiltersCollectedEvent;
use HeimrichHannot\FlareBundle\Filter\Collector\FilterCollectors;
use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\Specification\DataSource\ListDataSourceInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates a ListSpecification based on a ListDataSourceInterface.
 * Responsible for hydrating the specification and auto-collecting filters.
 */
final readonly class ListSpecificationFactory
{
    public function __construct(
        private FilterCollectors         $filterCollectors,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function create(ListDataSourceInterface $dataSource): ListSpecification
    {
        // Automatically collect filters (delegate to FilterCollectors)
        $filterCollection = $this->collectFilters($dataSource);

        // Allow modification of collected filters
        $event = new ListFiltersCollectedEvent($filterCollection, $dataSource);
        $this->eventDispatcher->dispatch($event);

        $specification = new ListSpecification(
            type: $dataSource->getListType(),
            dc: $dataSource->getListTable(),
            dataSource: $dataSource,
            filters: $event->filters, // Use possibly modified filters
        );

        $specification->setProperties($dataSource->getListData());

        return $specification;
    }

    private function collectFilters(ListDataSourceInterface $dataSource): FilterDefinitionCollection
    {
        $collector = $this->filterCollectors->match($dataSource);

        if (!$collector) {
            return new FilterDefinitionCollection();
        }

        return $collector->collect($dataSource) ?? new FilterDefinitionCollection();
    }
}