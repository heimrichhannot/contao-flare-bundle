<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Engine\Projector\InteractiveProjector;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\GroupsEntriesTrait;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType\EventsListType;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\View\InteractiveEventsView;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class EventsInteractiveProjector extends InteractiveProjector
{
    use GroupsEntriesTrait;

    public function supports(ListSpecification $spec, ContextInterface $context): bool
    {
        return $spec->type === EventsListType::TYPE && $context instanceof InteractiveContext;
    }

    public function priority(ListSpecification $spec, ContextInterface $context): int
    {
        return 100;
    }

    public function project(ListSpecification $spec, ContextInterface $context): InteractiveEventsView
    {
        $interactiveView = parent::project($spec, $context);

        // todo(@ericges): this prolly doesn't do the trick. gotta override fetch method :(

        return new InteractiveEventsView($interactiveView);
    }
}