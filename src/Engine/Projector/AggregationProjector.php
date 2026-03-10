<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\AggregationView;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

/**
 * @implements ProjectorInterface<AggregationView>
 */
class AggregationProjector extends AbstractProjector
{
    public function supports(ListSpecification $list, ContextInterface $context): bool
    {
        return $context instanceof AggregationContext;
    }

    public function project(ListSpecification $list, ContextInterface $context): AggregationView
    {
        \assert($context instanceof AggregationContext, '$config must be an instance of AggregationConfig');

        $queryConfig = $this->createListQueryConfig($list, $context);

        return new AggregationView(
            fetchCount: fn (): int => $this->fetchCount($queryConfig)
        );
    }

    protected function createListQueryConfig(
        ListSpecification  $spec,
        AggregationContext $config,
        ?array             $filterValues = null,
    ): ListQueryConfig {
        $filterValues ??= $this->gatherFilterValues($spec, $config->getFilterValues());

        return new ListQueryConfig(
            list: $spec,
            context: $config,
            filterValues: $filterValues,
            isCounting: true,
        );
    }

    /**
     * @throws FlareException If the item provider throws an exception.
     */
    public function fetchCount(ListQueryConfig $queryConfig): int
    {
        try
        {
            $qb = $this->createQueryBuilder($queryConfig);

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