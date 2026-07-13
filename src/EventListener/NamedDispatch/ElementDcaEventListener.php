<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\ElementDcaEvent;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ElementDcaEventListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener(priority: -200)]
    public function __invoke(ElementDcaEvent $event): void
    {
        $prefix = $event->context->table === FilterModel::getTable() ? 'filter_element' : 'list';
        $eventName = "flare.{$prefix}.{$event->context->type}.dca";

        $this->eventDispatcher->dispatch(event: $event, eventName: $eventName);
    }
}
