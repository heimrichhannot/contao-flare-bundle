<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\Projector\InteractiveProjector;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\GroupsEntriesTrait;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType\EventsListType;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Loader\EventsInteractiveLoader;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\View\InteractiveEventsView;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGeneratorInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\FormInterface;

class EventsInteractiveProjector extends InteractiveProjector
{
    use GroupsEntriesTrait;

    public function supports(ListSpecification $list, ContextInterface $context): bool
    {
        return $list->type === EventsListType::TYPE && $context instanceof InteractiveContext;
    }

    public function priority(ListSpecification $list, ContextInterface $context): int
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