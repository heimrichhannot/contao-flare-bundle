<?php

namespace HeimrichHannot\FlareBundle\Projector;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Context\AggregationConfig;
use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\View\AggregationView;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

/**
 * @implements ProjectorInterface<AggregationView>
 */
class AggregationProjector extends AbstractProjector
{
    public function __construct(
        private readonly Connection       $connection,
        private readonly ListQueryManager $listQueryManager,
    ) {}

    public function supports(ContextConfigInterface $config): bool
    {
        return $config instanceof AggregationConfig;
    }

    public function project(ListSpecification $spec, ContextConfigInterface $config): AggregationView
    {
        \assert($config instanceof AggregationConfig, '$config must be an instance of AggregationConfig');

        $filterValues = $this->gatherFilterValues($spec, $config->getFilterValues());

        return new AggregationView(
            fetchCount: function () use ($spec, $config, $filterValues): int {
                return $this->fetchCount($spec, $config, $filterValues);
            }
        );
    }

    /**
     * @throws FlareException If the item provider throws an exception.
     */
    public function fetchCount(ListSpecification $spec, AggregationConfig $config, array $filterValues): int
    {
        try
        {
            $listQueryBuilder = $this->listQueryManager->prepare($spec);

            $query = $this->listQueryManager->populate(
                listQueryBuilder: $listQueryBuilder,
                listSpecification: $spec,
                contextConfig: $config,
                filterValues: $filterValues,
                isCounting: true
            );

            if (!$query->isAllowed()) {
                return 0;
            }

            $result = $query->execute($this->connection);

            $count = $result->fetchOne() ?: 0;

            $result->free();

            return $count;
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