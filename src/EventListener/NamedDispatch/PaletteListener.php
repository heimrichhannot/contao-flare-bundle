<?php

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Enum\PaletteContainer;
use HeimrichHannot\FlareBundle\Event\PaletteEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener(priority: -200)]
readonly class PaletteListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function __invoke(PaletteEvent $event): void
    {
        match ($event->getPaletteContainer()) {
            PaletteContainer::FILTER => $this->dispatchFilterPaletteEvent($event),
            PaletteContainer::LIST => $this->dispatchListPaletteEvent($event),
        };
    }

    private function dispatchFilterPaletteEvent(PaletteEvent $event): void
    {
        if ($filterElementAlias = $event->getPaletteConfig()->getFilterModel()?->type)
        {
            $eventName = "flare.filter_element.{$filterElementAlias}.palette";
            $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
        }
    }

    private function dispatchListPaletteEvent(PaletteEvent $event): void
    {
        $eventName = "flare.list.{$event->getPaletteConfig()->getListModel()->type}.palette";
        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }
}