<?php

declare(strict_types=1);

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
            filters: $filterCollection,
        );

        $specification->setProperties($dataSource->getListData());

        $event = $this->eventDispatcher->dispatch(new ListSpecificationCreatedEvent($specification));

        return $event->listSpecification;
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