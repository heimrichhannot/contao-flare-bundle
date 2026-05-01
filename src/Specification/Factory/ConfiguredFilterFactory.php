<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Specification\Factory;

use HeimrichHannot\FlareBundle\Event\ConfiguredFilterCreatedEvent;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\DataSource\FilterDataSourceInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ConfiguredFilterFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function create(FilterDataSourceInterface $dataSource): ConfiguredFilter
    {
        $filter = new ConfiguredFilter(
            type: $dataSource->getFilterType(),
            intrinsic: $dataSource->isFilterIntrinsic(),
            alias: $dataSource->getFilterFormName(),
            targetAlias: $dataSource->getFilterTargetAlias(),
            dataSource: $dataSource,
            rawData: $dataSource->getFilterData(),
        );

        $event = $this->eventDispatcher->dispatch(new ConfiguredFilterCreatedEvent($filter));

        return $event->configuredFilter;
    }
}