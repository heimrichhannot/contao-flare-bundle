<?php

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FetchCountEvent;
use HeimrichHannot\FlareBundle\Event\FetchListEntriesEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FetchListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function onFetchCountEvent(FetchCountEvent $event): void
    {
        $eventName = "flare.list.{$event->getListSpecification()->type}.fetch_count";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }

    #[AsEventListener(priority: -200)]
    public function onFetchListEntriesEvent(FetchListEntriesEvent $event): void
    {
        $eventName = "flare.list.{$event->getListSpecification()->type}.fetch_entries";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }
}