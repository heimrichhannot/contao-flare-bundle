<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FilterElementBuiltEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementBuildingEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementFormBuiltEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterElementListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function onFilterElementBuiltEvent(FilterElementBuiltEvent $event): void
    {
        if (!$type = $event->getContext()->filter->getElementType()) {
            return;
        }

        $this->eventDispatcher->dispatch(event: $event, eventName: "flare.filter_element.{$type}.built");
    }

    #[AsEventListener(priority: -200)]
    public function onFilterElementBuildingEvent(FilterElementBuildingEvent $event): void
    {
        if (!$type = $event->getContext()->filter->getElementType()) {
            return;
        }

        $this->eventDispatcher->dispatch(event: $event, eventName: "flare.filter_element.{$type}.building");
    }

    #[AsEventListener(priority: -200)]
    public function onFilterElementFormBuiltEvent(FilterElementFormBuiltEvent $event): void
    {
        if (!$type = $event->getContext()->filter->getElementType()) {
            return;
        }

        $this->eventDispatcher->dispatch(event: $event, eventName: "flare.filter_element.{$type}.form_built");
    }
}
