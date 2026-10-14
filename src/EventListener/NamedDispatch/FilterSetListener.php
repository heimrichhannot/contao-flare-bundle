<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FilterSetBuildEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterSetListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function onFilterSetBuildEvent(FilterSetBuildEvent $event): void
    {
        $eventName = "flare.filter_set.{$event->formName}.build";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }
}
