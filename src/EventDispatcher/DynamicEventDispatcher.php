<?php

namespace HeimrichHannot\FlareBundle\EventDispatcher;

use HeimrichHannot\FlareBundle\Event\FlareDynamicEventInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A specialized event dispatcher that manages dynamic event names based on the FlareDynamicEventInterface.
 * It wraps an underlying EventDispatcherInterface to delegate the actual event dispatching.
 */
readonly class DynamicEventDispatcher implements EventDispatcherInterface
{
    public function __construct(private EventDispatcherInterface $inner) {}

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $event = $this->inner->dispatch(event: $event, eventName: $eventName);

        if (!$event instanceof FlareDynamicEventInterface)
        {
            return $event;
        }

        $originalEventName = $eventName ?? $event::class;
        $dynamicEventName = $event->getEventName();

        if (!$dynamicEventName || $dynamicEventName === $originalEventName)
        {
            return $event;
        }

        return $this->inner->dispatch(event: $event, eventName: $dynamicEventName);
    }
}