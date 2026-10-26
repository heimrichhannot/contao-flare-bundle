<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FilterControlConfigureTransformersEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementConfigureTransformersEvent;
use HeimrichHannot\FlareBundle\Event\ListDriverConfigureTransformersEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class TransformersEventListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function onFilterElementConfigureTransformers(FilterElementConfigureTransformersEvent $event): void
    {
        $this->eventDispatcher->dispatch(
            event: $event,
            eventName: "flare.filter_element.{$event->type}.transformers",
        );
    }

    #[AsEventListener(priority: -200)]
    public function onFilterControlConfigureTransformers(FilterControlConfigureTransformersEvent $event): void
    {
        $this->eventDispatcher->dispatch(
            event: $event,
            eventName: "flare.filter_control.{$event->type}.transformers",
        );
    }

    #[AsEventListener(priority: -200)]
    public function onListDriverConfigureTransformers(ListDriverConfigureTransformersEvent $event): void
    {
        if (!$event->type) {
            return;
        }

        $this->eventDispatcher->dispatch(
            event: $event,
            eventName: "flare.list_driver.{$event->type}.transformers",
        );
    }
}
