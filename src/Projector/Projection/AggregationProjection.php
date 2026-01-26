<?php

namespace HeimrichHannot\FlareBundle\Projector\Projection;

use HeimrichHannot\FlareBundle\Event\FetchCountEvent;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\ListItemProvider\ListItemProviderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AggregationProjection implements ProjectionInterface
{
    private int $count;

    public function __construct(
        private readonly EventDispatcherInterface  $eventDispatcher,
        private readonly ListContext               $listContext,
        private readonly ListDefinition            $listDefinition,
        private readonly ListItemProviderInterface $itemProvider,
        private readonly ListQueryBuilder          $listQueryBuilder,
    ) {}

    public function getCount(): int
    {
        if (isset($this->count)) {
            return $this->count;
        }

        $event = $this->eventDispatcher->dispatch(
            new FetchCountEvent(
                listContext: $this->listContext,
                listDefinition: $this->listDefinition,
                itemProvider: $this->itemProvider,
                listQueryBuilder: $this->listQueryBuilder,
            )
        );

        $itemProvider = $event->getItemProvider();
        $listQueryBuilder = $event->getListQueryBuilder();

        $count = $itemProvider->fetchCount(
            listQueryBuilder: $listQueryBuilder,
            listDefinition: $this->listDefinition,
            listContext: $this->listContext,
        );

        return $this->count = $count;
    }
}