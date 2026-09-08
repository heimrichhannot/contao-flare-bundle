<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FilterFormBuiltEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterFormListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function onFilterFormBuiltEvent(FilterFormBuiltEvent $event): void
    {
        if (!$type = $event->context->filter->type) {
            return;
        }

        $this->eventDispatcher->dispatch(event: $event, eventName: "flare.filter_form.{$type}.built");
    }
}
