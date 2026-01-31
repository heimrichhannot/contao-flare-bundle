<?php

namespace HeimrichHannot\FlareBundle\Specification\Factory;

use HeimrichHannot\FlareBundle\Collection\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\Event\ListSpecificationCreatedEvent;
use HeimrichHannot\FlareBundle\Registry\FilterCollectorRegistry;
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
        private FilterCollectorRegistry  $filterCollectors,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function create(ListDataSourceInterface $dataSource): ListSpecification
    {
        // Automatically collect filters (delegate to FilterCollectorRegistry)
        $filterCollection = $this->collectFilters($dataSource);

        $specification = new ListSpecification(
            type: $dataSource->getListType(),
            dc: $dataSource->getListTable(),
            dataSource: $dataSource,
            filters: $filterCollection, // Use possibly modified filters
        );

        $specification->setProperties($dataSource->getListData());

        // Allow modification of collected filters
        $event = new ListSpecificationCreatedEvent($specification);
        $this->eventDispatcher->dispatch($event);

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