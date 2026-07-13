<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Specification\Factory;

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
        $specification = new ListSpecification(
            type: $dataSource->getListType(),
            dc: $dataSource->getListTable(),
            dataSource: $dataSource,
        );

        // Automatically collect filters (delegate to FilterCollectorRegistry)
        foreach ($this->collectFilters($dataSource) as $key => $filter) {
            $specification->addFilter($filter, $key);
        }

        $specification->setProperties($dataSource->getListData());

        $event = $this->eventDispatcher->dispatch(new ListSpecificationCreatedEvent($specification));

        return $event->listSpecification;
    }

    /**
     * @return array<string, \HeimrichHannot\FlareBundle\Filter\Filter>
     */
    private function collectFilters(ListDataSourceInterface $dataSource): array
    {
        return $this->filterCollectors->match($dataSource)?->collect($dataSource) ?? [];
    }
}
