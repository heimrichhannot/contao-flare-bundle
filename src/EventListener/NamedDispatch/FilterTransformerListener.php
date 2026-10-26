<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FilterTransformerEvent;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class FilterTransformerListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function __invoke(FilterTransformerEvent $event): void
    {
        $prefix = $event->owner instanceof FilterControlInterface ? 'filter_control' : 'filter_element';

        $this->eventDispatcher->dispatch(event: $event, eventName: "flare.{$prefix}.{$event->type}.transformers");
    }
}
