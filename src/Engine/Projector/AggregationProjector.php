<?php

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\AggregationView;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Manager\ListQueryManager;
use HeimrichHannot\FlareBundle\Query\Factory\ListQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

/**
 * @implements ProjectorInterface<AggregationView>
 */
class AggregationProjector extends AbstractProjector
{
    public function __construct(
        private readonly ListQueryManager        $listQueryManager,
        private readonly ListQueryBuilderFactory $listQueryBuilderFactory,
    ) {}

    public function supports(ListSpecification $spec, ContextInterface $config): bool
    {
        return $config instanceof AggregationContext;
    }

    public function project(ListSpecification $spec, ContextInterface $config): AggregationView
    {
        \assert($config instanceof AggregationContext, '$config must be an instance of AggregationConfig');

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
    public function fetchCount(ListSpecification $spec, AggregationContext $config, array $filterValues): int
    {
        try
        {
            $listQueryBuilder = $this->listQueryBuilderFactory->create($spec);

            $qb = $this->listQueryManager->populate(
                listQueryBuilder: $listQueryBuilder,
                listSpecification: $spec,
                contextConfig: $config,
                filterValues: $filterValues,
                isCounting: true
            );

            if (!$qb) {
                return 0;
            }

            $result = $qb->executeQuery();

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