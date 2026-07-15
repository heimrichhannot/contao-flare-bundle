<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\ListBuildEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListBuildListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function __invoke(ListBuildEvent $event): void
    {
        $reference = $event->builder->getDriverReference();

        if ($reference->inline) {
            return;
        }

        $this->eventDispatcher->dispatch(event: $event, eventName: "flare.list.{$reference->type}.build");
    }
}
