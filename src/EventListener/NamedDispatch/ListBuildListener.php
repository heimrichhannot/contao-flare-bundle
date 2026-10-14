<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\ListBuildEvent;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListBuildListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ListDriverRegistry       $listDriverRegistry,
    ) {}

    #[AsEventListener(priority: -200)]
    public function __invoke(ListBuildEvent $event): void
    {
        foreach ($this->listDriverRegistry->getTypes($event->builder->getDriver()) as $type)
        {
            $this->eventDispatcher->dispatch(event: $event, eventName: "flare.list.{$type}.build");

            if ($event->isPropagationStopped()) {
                break;
            }
        }
    }
}
