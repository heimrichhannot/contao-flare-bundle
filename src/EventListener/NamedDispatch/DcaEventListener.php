<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FilterControlBuildDcaEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementBuildDcaEvent;
use HeimrichHannot\FlareBundle\Event\ListDriverBuildDcaEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class DcaEventListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function onListDriverDca(ListDriverBuildDcaEvent $event): void
    {
        $this->eventDispatcher->dispatch(
            event: $event,
            eventName: "flare.list_driver.{$event->context->type}.dca",
        );
    }

    #[AsEventListener(priority: -200)]
    public function onFilterElementDca(FilterElementBuildDcaEvent $event): void
    {
        $this->eventDispatcher->dispatch(
            event: $event,
            eventName: "flare.filter_element.{$event->context->type}.dca",
        );
    }

    #[AsEventListener(priority: -200)]
    public function onFilterControlDca(FilterControlBuildDcaEvent $event): void
    {
        $this->eventDispatcher->dispatch(event: $event, eventName: "flare.filter_control.{$event->type}.dca");
    }
}
