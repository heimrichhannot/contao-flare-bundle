<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\ListDriverTransformerEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListDriverTransformerListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function __invoke(ListDriverTransformerEvent $event): void
    {
        if (!$event->type) {
            return;
        }

        $this->eventDispatcher->dispatch(event: $event, eventName: "flare.list_driver.{$event->type}.transformers");
    }
}
