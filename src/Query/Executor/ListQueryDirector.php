<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Query\Executor;

use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Event\ModifyListQueryStructEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterContextFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\Factory\QueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\FilterConditions;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Util\CreatesFilterExceptionTrait;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ListQueryDirector
{
    use CreatesFilterExceptionTrait;

    public function __construct(
        private EventDispatcherInterface    $eventDispatcher,
        private FilterContextFactory        $filterContextFactory,
        private FilterQueryBuilderFactory   $filterQueryBuilderFactory,
        private ListExecutionContextFactory $listExecutionContextFactory,
        private QueryBuilderFactory         $queryBuilderFactory,
        private LoggerInterface             $logger,
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

            $filterQueryBuilders = $this->invokeFilters($config);
            $filterQueries = $this->buildFilterConditions($filterQueryBuilders);

            $event = new ModifyListQueryStructEvent(
                filterQueries: $filterQueries,
                config: $config,
                tableAliasRegistry: $registry,
                queryStruct: $struct,
            );

            /** @var ModifyListQueryStructEvent $event */
            $event = $this->eventDispatcher->dispatch($event);

            return $this->queryBuilderFactory->create($event->queryStruct);
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
     * @return FilterConditionsBuilder[]
     *
     * @throws AbortFilteringException
     * @throws FilterException
     * @throws FlareException
     */
    public function invokeFilters(ListQueryConfig $options): array
    {
        $filterQueryBuilders = [];
        $list = $options->list;

        foreach ($list->filters as $filter)
        {
            $context = $this->filterContextFactory->create(
                list: $list,
                filter: $filter,
                engineContext: $options->context
            );

            if (!$builders = $this->buildFilterConditionsBuilders($context)) {
                continue;
            }

            \array_push($filterQueryBuilders, ...$builders);
        }

        return $filterQueryBuilders;
    }

    /**
     * @return FilterConditionsBuilder[]
     * @throws AbortFilteringException
     * @throws FilterException
     */
    private function buildFilterConditionsBuilders(FilterContext $context): array
    {
        $filterQueryBuilders = [];

        foreach ($context->formula->propositions as $proposition)
        // todo: this should be simplified, either FilterConditionsBuilderFactory like FilterContextFactory
        //   or skip collecting the builders and build right away
        //   in any case: we need to handle the AbortFilteringException and FilterException properly
        {
            $filterQueryBuilder = $this->filterQueryBuilderFactory->create($proposition->targetAlias);

            try
            {
                $proposition->predicate->buildConditions($filterQueryBuilder, $proposition->options);
            }
            catch (AbortFilteringException $e)
            {
                throw $e;
            }
            catch (FilterException $e)
            {
                throw $this->createFilterException($e, $context->filter, $proposition->predicateClass . '::buildConditions');
            }
            catch (\Throwable $e)
            {
                throw new FilterException($e->getMessage(), code: $e->getCode(), previous: $e,
                    method: $proposition->predicateClass, source: $context->filter->source ?: 'filter inlined');
            }

            $filterQueryBuilders[] = $filterQueryBuilder;
        }

        return $filterQueryBuilders;
    }

    /**
     * @param FilterConditionsBuilder[] $filterQueryBuilders
     * @return FilterConditions[]
     */
    private function buildFilterConditions(array $filterQueryBuilders): array
    {
        $filterQueries = [];

        foreach (\array_values($filterQueryBuilders) as $i => $filterQueryBuilder)
        {
            $filterQueries[] = $filterQueryBuilder->build((string) ($i + 1));
        }

        return $filterQueries;
    }
}
