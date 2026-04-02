<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query\Executor;

use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\Factory\QueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\FilterQuery;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListQueryDirector
{
    public function __construct(
        private EventDispatcherInterface    $eventDispatcher,
        private FilterExecutor              $filterExecutor,
        private ListExecutionContextFactory $listExecutionContextFactory,
        private QueryBuilderFactory         $queryBuilderFactory,
        private LoggerInterface $logger
    ) {}

    /**
     * Creates a query builder based on the provided list query configuration.
     *
     * @param ListQueryConfig $config Configuration for creating the query.
     * @return QueryBuilder|null Returns the constructed query builder, or null if filtering is aborted.
     *
     * @throws FlareException When an error occurs while creating the ListExecutionContext.
     * @throws FilterException When an error occurs while filtering the list.
     */
    public function createQueryBuilder(ListQueryConfig $config): ?QueryBuilder
    {
        try
        {
            $executionContext = $this->listExecutionContextFactory->create($config->list);

            $registry = $executionContext->tableAliasRegistry;
            $struct = $executionContext->queryStruct;

            $filterQueryBuilders = $this->filterExecutor->invokeFilters($config);
            $filterQueries = $this->buildFilterQueries($filterQueryBuilders);

            $this->eventDispatcher->dispatch(new ModifyListQueryStructEvent(
                filterQueries: $filterQueries,
                config: $config,
                tableAliasRegistry: $registry,
                queryStruct: $struct,
            ));

            return $this->queryBuilderFactory->create($struct);
        }
        catch (AbortFilteringException $e)
        {
            $this->logger->debug($e->getMessage(), [
                'method' => __METHOD__,
                'exception' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * @param FilterQueryBuilder[] $filterQueryBuilders
     * @return FilterQuery[]
     */
    public function buildFilterQueries(array $filterQueryBuilders): array
    {
        $filterQueries = [];

        foreach (\array_values($filterQueryBuilders) as $i => $filterQueryBuilder)
        {
            $filterQueries[] = $filterQueryBuilder->build((string) ($i + 1));
        }

        return $filterQueries;
    }
}