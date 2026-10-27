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
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
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
     * A list that recorded failures while it was built has failed as a whole (SPEC_FILTER_FORMS.md
     * §8.1): it is never queried, in any context.
     *
     * @param ListQueryConfig $config Configuration for creating the query.
     * @return QueryBuilder|null Returns the constructed query builder, or null if filtering is aborted.
     *
     * @throws FlareException When an error occurs while creating the ListExecutionContext.
     * @throws FilterException When the list has failed, or a filter without a control fails.
     */
    public function createQueryBuilder(ListQueryConfig $config): ?QueryBuilder
    {
        if ($failures = $config->list->failures)
        {
            throw new FilterException(
                \sprintf('[FLARE] The list has failed: %s', \implode('; ', $failures)),
                method: __METHOD__,
                source: $config->list->source ?? 'list inlined',
            );
        }

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
     * Invokes every filter of the list with its value and collects the condition builders.
     *
     * The value is taken by the filter's role (SPEC_FILTER_FORMS.md §4.2, §8): with a control, only
     * the form's value map applies; without one, the filter's fixed value applies in every context.
     *
     * A failing filter fails by what it bounds (§4.2, §8.1). A filter with a control that fails with
     * a value is retried without it: the element's own config (e.g. a whitelist) still bounds the
     * whole list, only the user's or initial value is dropped with a warning (fail open). A filter
     * that fails without a value, or a filter without a control, fails the list (fail closed).
     *
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
        $values = $options->filterValues;

        foreach ($list->filters as $filter)
        {
            $value = $filter->control !== null ? ($values[$filter->alias] ?? null) : $filter->value;
            $scope = new ElementScope($list, $filter, $options->context);

            try
            {
                $builders = $this->buildFilterConditionsBuilders($scope, $value);
            }
            catch (FilterException $e)
            {
                if ($filter->control === null || $value === null) {
                    throw $e;
                }

                // Throws if the element's config is broken regardless of the value: fail closed.
                $builders = $this->buildFilterConditionsBuilders($scope, null);

                $this->logger->warning(\sprintf(
                    '[FLARE] Filter "%s" (%s) failed with its value, which is dropped: %s',
                    $filter->alias,
                    $filter->source ?: 'filter inlined',
                    $e->getMessage(),
                ), ['method' => __METHOD__, 'exception' => $e]);
            }

            \array_push($filterQueryBuilders, ...$builders);
        }

        return $filterQueryBuilders;
    }

    /**
     * @return FilterConditionsBuilder[]
     *
     * @throws AbortFilteringException
     * @throws FilterException Wrapped with the filter's source.
     * @throws FlareException
     */
    private function buildFilterConditionsBuilders(ElementScope $scope, ?ValueInterface $value): array
    {
        $filter = $scope->filter;

        try
        {
            $context = $this->filterContextFactory->create($scope, $value);
        }
        catch (\Exception $e)
        {
            throw $this->wrapFilterFailure($e, $filter, $filter->element->component::class . '::buildContext');
        }

        $filterQueryBuilders = [];

        foreach ($context->formula->propositions as $proposition)
        // todo: this should be simplified, either FilterConditionsBuilderFactory like FilterContextFactory
        //   or skip collecting the builders and build right away
        {
            $filterQueryBuilder = $this->filterQueryBuilderFactory->create($proposition->targetAlias);

            try
            {
                $proposition->predicate->buildConditions($filterQueryBuilder, $proposition->options);
            }
            catch (\Exception $e)
            {
                throw $this->wrapFilterFailure($e, $filter, $proposition->predicateClass . '::buildConditions');
            }

            $filterQueryBuilders[] = $filterQueryBuilder;
        }

        return $filterQueryBuilders;
    }

    /**
     * The exception to rethrow for a failure while applying a filter: a {@see FilterException} is
     * wrapped with the filter's source, any other {@see FlareException} (incl. aborting) passes through
     * unchanged, and other exceptions become a {@see FilterException}. Errors (`\Error`) are programming
     * errors and are never caught.
     */
    private function wrapFilterFailure(\Exception $e, Filter $filter, string $method): FlareException
    {
        return match (true) {
            $e instanceof FilterException => $this->createFilterException($e, $filter, $method),
            $e instanceof FlareException => $e,
            default => new FilterException($e->getMessage(), code: $e->getCode(), previous: $e,
                method: $method, source: $filter->source ?: 'filter inlined'),
        };
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
