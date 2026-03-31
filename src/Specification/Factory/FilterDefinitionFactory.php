<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Specification\Factory;

use HeimrichHannot\FlareBundle\Event\FilterDefinitionCreatedEvent;
use HeimrichHannot\FlareBundle\Specification\DataSource\FilterDataSourceInterface;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class FilterDefinitionFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function create(FilterDataSourceInterface $dataSource): FilterDefinition
    {
        $definition = new FilterDefinition(
            type: $dataSource->getFilterType(),
            intrinsic: $dataSource->isFilterIntrinsic(),
            alias: $dataSource->getFilterFormName(),
            targetAlias: $dataSource->getFilterTargetAlias(),
            dataSource: $dataSource,
        );

        $definition->setProperties($dataSource->getFilterData());

        $event = $this->eventDispatcher->dispatch(new FilterDefinitionCreatedEvent($definition));

        return $event->filterDefinition;
    }
}