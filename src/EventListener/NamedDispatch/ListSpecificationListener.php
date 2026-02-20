<?php

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\ListSpecificationCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListSpecificationListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function onListSpecificationCreated(ListSpecificationCreatedEvent $event): void
    {
        $eventName = "flare.list_type.{$event->listSpecification->type}.list_specification_created";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }
}