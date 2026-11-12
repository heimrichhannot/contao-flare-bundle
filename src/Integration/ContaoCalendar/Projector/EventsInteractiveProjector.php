<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Projector;

use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Engine\Intent\InteractiveIntent;
use HeimrichHannot\FlareBundle\Engine\ListEntry\EntryCollector;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\Projector\InteractiveProjector;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\GroupsEntriesTrait;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListDriver\EventsListDriver;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Loader\EventsInteractiveLoader;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\View\InteractiveEventsView;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use Symfony\Component\Form\FormInterface;

class EventsInteractiveProjector extends InteractiveProjector
{
    use GroupsEntriesTrait;

    public function supports(ListCompound $list, IntentInterface $intent): bool
    {
        return $list->driver instanceof EventsListDriver && $intent instanceof InteractiveIntent;
    }

    public function priority(ListCompound $list, IntentInterface $intent): int
    {
        return 100;
    }

    protected function createLoader(InteractiveLoaderConfig $config): InteractiveLoaderInterface
    {
        return new EventsInteractiveLoader(
            config: $config,
            listQueryDirector: $this->getListQueryDirector(),
        );
    }

    protected function createView(
        InteractiveLoaderInterface $loader,
        FormInterface              $form,
        Paginator                  $paginator,
        EntryCollector             $collector,
        EntryLookupInterface       $lookup,
        int                        $totalItems,
        bool                       $failed = false,
    ): InteractiveEventsView {
        return new InteractiveEventsView(
            loader: $loader,
            form: $form,
            paginator: $paginator,
            collector: $collector,
            lookup: $lookup,
            totalItems: $totalItems,
            failed: $failed,
        );
    }
}
