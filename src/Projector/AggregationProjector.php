<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Manager\ListItemProviderManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Projector\Projection\AggregationProjection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AggregationProjector extends AbstractProjector
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ListItemProviderManager  $itemProviderManager,
        private readonly ListQueryManager         $listQueryManager,
    ) {}

    public static function getContext(): string
    {
        return ListContext::AGGREGATION;
    }

    protected function execute(ListContext $context, ListDefinition $listDefinition): AggregationProjection
    {
        $itemProvider = $this->itemProviderManager->ofList($listDefinition);
        $listQueryBuilder = $this->listQueryManager->prepare($listDefinition);

        return new AggregationProjection(
            eventDispatcher: $this->eventDispatcher,
            listContext: $context,
            listDefinition: $listDefinition,
            itemProvider: $itemProvider,
            listQueryBuilder: $listQueryBuilder,
        );
    }
}