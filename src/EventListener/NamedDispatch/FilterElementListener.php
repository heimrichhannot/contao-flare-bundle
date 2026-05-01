<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FilterElementBuiltEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementBuildingEvent;
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
        $type = $event->getInvocation()->getConfiguredFilter()->getElementType();
        $eventName = "flare.filter_element.{$type}.built";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }

    #[AsEventListener(priority: -200)]
    public function onFilterElementBuildingEvent(FilterElementBuildingEvent $event): void
    {
        $type = $event->getInvocation()->getConfiguredFilter()->getElementType();
        $eventName = "flare.filter_element.{$type}.building";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }
}