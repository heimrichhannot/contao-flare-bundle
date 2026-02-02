<?php

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Projector\AbstractProjector;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType\EventsListType;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class EventsAggregationProjector extends AbstractProjector
{
    public function supports(ListSpecification $spec, ContextInterface $config): bool
    {
        return $spec->type === EventsListType::TYPE && $config instanceof AggregationContext;
    }

    public function priority(ListSpecification $spec, ContextInterface $config): int
    {
        return 100;
    }

    public function project(ListSpecification $spec, ContextInterface $config): ViewInterface
    {
        // TODO: Implement project() method.
    }
}