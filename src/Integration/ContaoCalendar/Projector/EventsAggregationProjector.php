<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\Projector\AggregationProjector;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\GroupsEntriesTrait;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType\EventsListType;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Loader\EventsAggregationLoader;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class EventsAggregationProjector extends AggregationProjector
{
    use GroupsEntriesTrait;

    public function supports(ListSpecification $list, ContextInterface $context): bool
    {
        return $list->type === EventsListType::TYPE && $context instanceof AggregationContext;
    }

    public function priority(ListSpecification $list, ContextInterface $context): int
    {
        return 100;
    }

    protected function createLoader(AggregationLoaderConfig $config): AggregationLoaderInterface
    {
        return new EventsAggregationLoader(
            config: $config,
            listQueryDirector: $this->getListQueryDirector(),
        );
    }
}