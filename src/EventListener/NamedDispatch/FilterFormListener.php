<?php

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FilterFormBuildEvent;
use HeimrichHannot\FlareBundle\Event\FilterFormChildOptionsEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterFormListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -100)]
    public function onFilterFormChildOptionsEvent(FilterFormChildOptionsEvent $event): void
    {
        $eventName = "flare.form.{$event->parentFormName}.child.{$event->formName}.options";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }

    #[AsEventListener(priority: -100)]
    public function onFilterFormBuildEvent(FilterFormBuildEvent $event): void
    {
        $eventName = "flare.form.{$event->formName}.build";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }
}