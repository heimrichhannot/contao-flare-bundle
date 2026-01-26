<?php

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokingEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterElementListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function onFilterElementInvokedEvent(FilterElementInvokedEvent $event): void
    {
        $eventName = "flare.filter_element.{$event->getFilter()->getFilterType()}.invoked";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }

    #[AsEventListener(priority: -200)]
    public function onFilterElementInvokingEvent(FilterElementInvokingEvent $event): void
    {
        $eventName = "flare.filter_element.{$event->getFilterDefinition()->getType()}.invoking";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }
}