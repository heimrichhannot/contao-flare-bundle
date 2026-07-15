<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\Projector\InteractiveProjector;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\GroupsEntriesTrait;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListDriver\EventsListDriver;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Loader\EventsInteractiveLoader;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\View\InteractiveEventsView;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGeneratorInterface;
use Symfony\Component\Form\FormInterface;

class EventsInteractiveProjector extends InteractiveProjector
{
    use GroupsEntriesTrait;

    public function supports(ListSpec $list, ContextInterface $context): bool
    {
        return $list->type === EventsListDriver::TYPE && $context instanceof InteractiveContext;
    }

    public function priority(ListSpec $list, ContextInterface $context): int
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
        InteractiveLoaderInterface  $loader,
        FormInterface               $form,
        Paginator                   $paginator,
        ReaderUrlGeneratorInterface $readerUrlGenerator,
        string                      $table,
        int                         $totalItems,
    ): InteractiveEventsView {
        return new InteractiveEventsView(
            loader: $loader,
            form: $form,
            paginator: $paginator,
            readerUrlGenerator: $readerUrlGenerator,
            table: $table,
            totalItems: $totalItems,
        );
    }
}
