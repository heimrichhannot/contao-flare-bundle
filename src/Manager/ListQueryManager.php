<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\ListType\PrepareListQueryInterface;
use HeimrichHannot\FlareBundle\Dto\FilterInvocationDto;
use HeimrichHannot\FlareBundle\Dto\ParameterizedSqlQuery;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokingEvent;
use HeimrichHannot\FlareBundle\Event\ListQueryPrepareEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Registry\Descriptor\ListTypeDescriptor;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ListQueryManager
{
    public const FILTER_OK = 0;
    public const FILTER_SKIP = 1;
    public const FILTER_FAIL = 2;
    public const ALIAS_MAIN = 'main';

    private array $prepCache = [];

    public function __construct(
        private readonly Connection                $connection,
        private readonly EventDispatcherInterface  $eventDispatcher,
        private readonly FilterQueryBuilderFactory $filterQueryBuilderFactory,
        private readonly ListTypeRegistry          $listTypeRegistry,
    ) {}

    /**
     * @throws FlareException
     */
    public function prepare(ListDefinition $list, ?bool $noCache = null): ListQueryBuilder
    {
        $doCache = !$noCache;

        $cacheKey = $list->hash();
        if ($doCache && $hit = $this->prepCache[$cacheKey] ?? null) {
            return clone $hit;
        }

        /** @var ListTypeDescriptor $listTypeDescriptor */
        if (!($listTypeDescriptor = $this->listTypeRegistry->get($list->type)) instanceof ListTypeDescriptor) {
            throw new FlareException(\sprintf('No list type registered for type "%s".', $list->type), method: __METHOD__);
        }

        if (!$mainTable = $list->dc ?? $listTypeDescriptor->getDataContainer()) {
            throw new FlareException('No data container table set.', method: __METHOD__);
        }

        $builder = new ListQueryBuilder(
            connection: $this->connection,
            mainTable: $mainTable,
            mainAlias: self::ALIAS_MAIN,
        );

        $builder->select('*', of: self::ALIAS_MAIN, allowAsterisk: true);
        $builder->groupBy('id', self::ALIAS_MAIN);

        $event = new ListQueryPrepareEvent(
            listDefinition: $list,
            listQueryBuilder: $builder
        );

        $listType = $listTypeDescriptor->getService();
        if ($listType instanceof PrepareListQueryInterface) {
            $listType->onListQueryPrepareEvent($event);
        }

        /** @var ListQueryPrepareEvent $event */
        $event = $this->eventDispatcher->dispatch($event);

        $builder = $event->getListQueryBuilder();

        $builder->select('id', of: self::ALIAS_MAIN, as: 'id');

        if ($doCache) {
            $this->prepCache[$cacheKey] = clone $builder;
        }

        return $builder;
    }

    /**
     * @throws FilterException
     */
    public function populate(
        ListQueryBuilder        $listQueryBuilder,
        FilterContextCollection $filters,
        ?string                 $order = null,
        ?int                    $limit = null,
        ?int                    $offset = null,
        bool                    $isCounting = false,
        bool                    $onlyId = false,
        ?array                  $select = null,
    ): ParameterizedSqlQuery {
        if (!Str::isValidSqlName($filters->getTable())) {
            throw new FilterException(
                \sprintf('[FLARE] Invalid table name: %s', $filters->getTable()), method: __METHOD__,
            );
        }

        try
        {
            $invoked = $this->invokeFilters(
                listQueryBuilder: $listQueryBuilder,
                filters: $filters,
            );
        }
        catch (AbortFilteringException)
        {
            return ParameterizedSqlQuery::noResult();
        }

        if (\is_array($select) && !$select)
        {
            $select = \array_unique(\array_filter(\array_map(function (string $column): ?string {
                if (!$column = \trim($column)) {
                    return null;
                }

                if (!Str::isValidSqlName($column)) {
                    return null;
                }

                return $this->connection->quoteIdentifier(self::ALIAS_MAIN . '.' . $column);
            }, $select)));
        }

        if (\is_array($select) && !$select)
        {
            return ParameterizedSqlQuery::noResult();
        }

        $aliasesUsed = \array_unique(\array_merge(
            \array_keys($invoked->tablesUsed),
            $listQueryBuilder->getMandatoryTableAliases(),
        ));

        $sqlQuery = $listQueryBuilder->buildQuery()->withFilteredJoins($aliasesUsed);

        $altSelect = match (true) {
            $isCounting => [
                \sprintf(
                    "COUNT(%s) AS %s",
                    (\count($sqlQuery->getJoins()) < 1)
                        ? '*'
                        : \sprintf('DISTINCT(%s)', $this->connection->quoteIdentifier(self::ALIAS_MAIN . '.id')),
                    $this->connection->quoteIdentifier('count')
                ),
            ],
            $onlyId => [
                \sprintf(
                    "%s AS %s",
                    $this->connection->quoteIdentifier(self::ALIAS_MAIN . '.id'),
                    $this->connection->quoteIdentifier('id')
                )
            ],
            \is_array($select) => $select,
            default => null,
        };

        $finalSQL = $sqlQuery->sqlify(
            select: $altSelect,
            conditions: $invoked->conditions
                ? $this->connection->createExpressionBuilder()->and(...$invoked->conditions)
                : '1 = 1',
            groupBy: $isCounting ? false : null,
            orderBy: $isCounting ? null : $order,
            limit: $isCounting ? null : $limit,
            offset: $isCounting ? null : $offset,
        );

        return new ParameterizedSqlQuery($finalSQL, $invoked->parameters, $invoked->types, true);
    }

    /**
     * @throws FilterException
     * @throws AbortFilteringException
     */
    public function invokeFilters(
        ListQueryBuilder $listQueryBuilder,
        FilterContextCollection $filters
    ): FilterInvocationDto {
        $invoked = new FilterInvocationDto();

        foreach ($filters as $i => $filter)
        {
            $targetAlias = ($filter->getDescriptor()->isTargeted() && $filter->getFilterModel()->targetAlias)
                ? $filter->getFilterModel()->targetAlias
                : self::ALIAS_MAIN;

            if (!$table = $listQueryBuilder->getTable($targetAlias)) {
                throw new FilterException('Invalid filter relation alias: ' . $targetAlias, method: __METHOD__);
            }

            $invoked->tablesUsed[$targetAlias] = $table;

            $filterQueryBuilder = $this->filterQueryBuilderFactory->create($targetAlias);

            $status = $this->invokeFilter(filterQueryBuilder: $filterQueryBuilder, filter: $filter);

            if ($status === self::FILTER_SKIP)
            {
                continue;
            }

            if ($status !== self::FILTER_OK)
                // If the filter failed, we stop building the query and throw an exception.
                // This is useful for cases where the filter is not applicable or has an error.
            {
                throw new AbortFilteringException();
            }

            $filterQuery = $filterQueryBuilder->build((string) $i);

            if (!$sql = $filterQuery->getSql())
            {
                continue;
            }

            $invoked->conditions[] = $sql;

            foreach ($filterQuery->getParams() as $key => $value) {
                $invoked->parameters[$key] = $value;
            }

            foreach ($filterQuery->getTypes() as $key => $value) {
                $invoked->types[$key] = $value;
            }
        }

        return $invoked;
    }

    /**
     * @throws FilterException
     */
    public function invokeFilter(
        FilterQueryBuilder $filterQueryBuilder,
        FilterContext      $filter,
        ?bool              $dispatchEvent = null,
    ): int {
        $config = $filter->getDescriptor();

        $service = $config->getService();
        $method = $config->getMethod() ?? '__invoke';

        if (!\method_exists($service, $method)) {
            return self::FILTER_SKIP;
        }

        $shouldInvoke = true;
        $callback = $service->{$method}(...);

        if ($dispatchEvent ?? true)
        {
            $event = $this->eventDispatcher->dispatch(new FilterElementInvokingEvent($filter, $callback, true));

            $shouldInvoke = $event->shouldInvoke();
            $callback = $event->getCallback();
        }

        if (!$shouldInvoke) {
            return self::FILTER_SKIP;
        }

        try
        {
            $callback($filter, $filterQueryBuilder);
        }
        catch (AbortFilteringException)
        {
            return self::FILTER_FAIL;
        }
        catch (FilterException $e)
        {
            $errorMethod = $e->getMethod() ?? ($service::class . '::' . $method);

            throw new FilterException(
                \sprintf('[FLARE] Query denied: %s', $e->getMessage()),
                code: $e->getCode(), previous: $e, method: $errorMethod,
                source: \sprintf('tl_flare_filter.id=%s', $filter->getFilterModel()?->id ?: 'unknown'),
            );
        }

        if ($dispatchEvent ?? true)
        {
            $this->eventDispatcher->dispatch(new FilterElementInvokedEvent($filter, $filterQueryBuilder, $method));
        }

        return self::FILTER_OK;
    }
}