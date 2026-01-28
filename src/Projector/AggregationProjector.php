<?php

namespace HeimrichHannot\FlareBundle\Projector;

use HeimrichHannot\FlareBundle\Context\AggregationConfig;
use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Event\FetchCountEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Manager\ListItemProviderManager;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\View\AggregationView;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProjectorInterface<AggregationView>
 */
class AggregationProjector extends AbstractProjector
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ListItemProviderManager  $itemProviderManager,
        private readonly ListQueryManager         $listQueryManager,
    ) {}


    public function supports(ContextConfigInterface $config): bool
    {
        return $config instanceof AggregationConfig;
    }

    public function project(ListSpecification $spec, ContextConfigInterface $config): AggregationView
    {
        \assert($config instanceof AggregationConfig);

        return new AggregationView(
            fetchCount: function () use ($spec, $config): int {
                return $this->fetchCount($spec, $config);
            }
        );
    }

    /**
     * @throws FlareException If the item provider throws an exception.
     */
    public function fetchCount(ListSpecification $spec, AggregationConfig $config): int
    {
        try
        {
            $itemProvider = $this->itemProviderManager->ofList($spec);
            $listQueryBuilder = $this->listQueryManager->prepare($spec);

            $event = $this->eventDispatcher->dispatch(
                new FetchCountEvent(
                    contextConfig: $config,
                    listSpecification: $spec,
                    itemProvider: $itemProvider,
                    listQueryBuilder: $listQueryBuilder,
                )
            );

            $itemProvider = $event->getItemProvider();
            $listQueryBuilder = $event->getListQueryBuilder();

            return $itemProvider->fetchCount(
                listQueryBuilder: $listQueryBuilder,
                listDefinition: $spec,
                contextConfig: $config,
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