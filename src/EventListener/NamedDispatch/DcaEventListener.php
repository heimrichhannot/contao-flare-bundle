<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FilterControlDcaEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementDcaEvent;
use HeimrichHannot\FlareBundle\Event\ListDriverDcaEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class DcaEventListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function onListDriverDca(ListDriverDcaEvent $event): void
    {
        $this->eventDispatcher->dispatch(
            event: $event,
            eventName: "flare.list_driver.{$event->context->type}.dca",
        );
    }

    #[AsEventListener(priority: -200)]
    public function onFilterElementDca(FilterElementDcaEvent $event): void
    {
        $this->eventDispatcher->dispatch(
            event: $event,
            eventName: "flare.filter_element.{$event->context->type}.dca",
        );
    }

    #[AsEventListener(priority: -200)]
    public function onFilterControlDca(FilterControlDcaEvent $event): void
    {
        $this->eventDispatcher->dispatch(event: $event, eventName: "flare.filter_control.{$event->type}.dca");
    }
}
