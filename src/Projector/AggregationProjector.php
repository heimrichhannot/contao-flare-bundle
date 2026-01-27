<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\Event\FetchCountEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Manager\ListItemProviderManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Projector\Projection\AggregationProjection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProjectorInterface<AggregationProjection>
 */
class AggregationProjector extends AbstractProjector
{
    public static function getContext(): string
    {
        return ListContext::AGGREGATION;
    }

    public static function getProjectionClass(): string
    {
        return AggregationProjection::class;
    }

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ListItemProviderManager  $itemProviderManager,
        private readonly ListQueryManager         $listQueryManager,
    ) {}

    protected function execute(ListContext $listContext, ListDefinition $listDefinition): AggregationProjection
    {
        return new AggregationProjection(
            fetchCount: function () use ($listContext, $listDefinition): int {
                return $this->fetchCount($listContext, $listDefinition);
            }
        );
    }

    /**
     * @throws FlareException If the item provider throws an exception.
     */
    public function fetchCount(ListContext $listContext, ListDefinition $listDefinition): int
    {
        try
        {
            $itemProvider = $this->itemProviderManager->ofList($listDefinition);
            $listQueryBuilder = $this->listQueryManager->prepare($listDefinition);

            $event = $this->eventDispatcher->dispatch(
                new FetchCountEvent(
                    listContext: $listContext,
                    listDefinition: $listDefinition,
                    itemProvider: $itemProvider,
                    listQueryBuilder: $listQueryBuilder,
                )
            );

            $itemProvider = $event->getItemProvider();
            $listQueryBuilder = $event->getListQueryBuilder();

            return $itemProvider->fetchCount(
                listQueryBuilder: $listQueryBuilder,
                listDefinition: $listDefinition,
                listContext: $listContext,
            );
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Throwable $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e, source: __METHOD__);
        }
    }
}