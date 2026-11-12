<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\ListDriverEntriesEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListDriverEntriesListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function __invoke(ListDriverEntriesEvent $event): void
    {
        $type = $event->list->type;

        if ($type === '') {
            return;
        }

        $this->eventDispatcher->dispatch(event: $event, eventName: "flare.list_driver.{$type}.entries");
    }
}
