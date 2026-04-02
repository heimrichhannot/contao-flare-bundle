<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;

readonly class AggregationLoader implements AggregationLoaderInterface
{
    public function __construct(
        private AggregationLoaderConfig $config,
        private ListQueryDirector       $listQueryDirector,
    ) {}

    /**
     * @throws FlareException If the item provider throws an exception.
     */
    public function fetchCount(): int
    {
        try
        {
            $queryConfig = new ListQueryConfig(
                list: $this->config->list,
                context: $this->config->context,
                filterValues: $this->config->filterValues,
                isCounting: true,
            );

            $qb = $this->listQueryDirector->createQueryBuilder($queryConfig);

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