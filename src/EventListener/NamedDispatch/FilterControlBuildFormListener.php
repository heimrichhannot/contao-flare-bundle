<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterControlBuildFormListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function onFilterControlBuildForm(FilterControlBuildFormEvent $event): void
    {
        if (!$type = $event->scope->filter->controlType) {
            return;
        }

        $this->eventDispatcher->dispatch(event: $event, eventName: "flare.filter_control.{$type}.build_form");
    }
}
