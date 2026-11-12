<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Projector;

use HeimrichHannot\FlareBundle\Engine\Intent\AggregationIntent;
use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\Projector\AggregationProjector;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\GroupsEntriesTrait;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListDriver\EventsListDriver;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Loader\EventsAggregationLoader;
use HeimrichHannot\FlareBundle\List\ListCompound;

class EventsAggregationProjector extends AggregationProjector
{
    use GroupsEntriesTrait;

    public function supports(ListCompound $list, IntentInterface $intent): bool
    {
        return $list->driver instanceof EventsListDriver && $intent instanceof AggregationIntent;
    }

    public function priority(ListCompound $list, IntentInterface $intent): int
    {
        return 100;
    }

    protected function createLoader(AggregationLoaderConfig $config): AggregationLoaderInterface
    {
        $listQueryDirector = $this->getListQueryDirector();

        return new EventsAggregationLoader(config: $config, listQueryDirector: $listQueryDirector);
    }
}
