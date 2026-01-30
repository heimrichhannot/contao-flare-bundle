<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contract\ListType\PrepareListQueryInterface;
use HeimrichHannot\FlareBundle\Dto\InvokeFiltersResult;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\PaginatedContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\SortableContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokingEvent;
use HeimrichHannot\FlareBundle\Event\ListQueryPrepareEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Filter\Invoker\FilterInvoker;
use HeimrichHannot\FlareBundle\Query\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Query\ParameterizedSqlQuery;
use HeimrichHannot\FlareBundle\Registry\Descriptor\ListTypeDescriptor;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ListQueryManager
{
    public const ALIAS_MAIN = 'main';

    private array $prepCache = [];

    public function __construct(
        private readonly Connection                $connection,
        private readonly EventDispatcherInterface  $eventDispatcher,
        private readonly FilterElementRegistry     $filterElementRegistry,
        private readonly FilterInvoker             $filterInvoker,
        private readonly FilterQueryBuilderFactory $filterQueryBuilderFactory,
        private readonly ListTypeRegistry          $listTypeRegistry,
    ) {}

    /**
     * @throws FlareException
     */
    public function prepare(ListSpecification $list, ?bool $noCache = null): ListQueryBuilder
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

        $event = new ListQueryPrepareEvent(listSpecification: $list, listQueryBuilder: $builder);

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
        ListQueryBuilder  $listQueryBuilder,
        ListSpecification $listSpecification,
        ContextInterface  $contextConfig,
        array             $filterValues = [],
        bool              $isCounting = false,
        bool              $onlyId = false,
        ?array            $select = null,
    ): ParameterizedSqlQuery {
        if (!Str::isValidSqlName($table = $listSpecification->dc)) {
            throw new FilterException(
                \sprintf('[FLARE] Invalid table name: %s', $table), method: __METHOD__,
            );
        }

        $order = null;
        $limit = null;
        $offset = null;

        if ($contextConfig instanceof SortableContextInterface)
        {
            $order = $contextConfig->getSortDescriptor()?->toSql($this->connection->quoteIdentifier(...));
        }

        if ($contextConfig instanceof PaginatedContextInterface)
        {
            $paginator = $contextConfig->getPaginatorConfig();
            $limit = $paginator->getItemsPerPage();
            $offset = $paginator->getOffset();
        }

        try
        {
            $invoked = $this->invokeFilters(
                listQueryBuilder: $listQueryBuilder,
                listSpecification: $listSpecification,
                contextConfig: $contextConfig,
                filterValues: $filterValues,
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
                ),
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
        ListQueryBuilder  $listQueryBuilder,
        ListSpecification $listSpecification,
        ContextInterface  $contextConfig,
        array             $filterValues = [],
    ): InvokeFiltersResult {
        $invoked = new InvokeFiltersResult();

        /**
         * @var int|string $key
         * @var FilterDefinition $filter
         */
        $i = 0;
        foreach ($listSpecification->getFilters()->all() as $key => $filter)
        {
            if (!$filterElementDescriptor = $this->filterElementRegistry->get($filter->getType())) {
                continue;
            }

            $targetAlias = ($filterElementDescriptor->isTargeted() && $filter->getTargetAlias())
                ? $filter->getTargetAlias()
                : self::ALIAS_MAIN;

            if (!$table = $listQueryBuilder->getTable($targetAlias)) {
                throw new FilterException('Invalid filter relation alias: ' . $targetAlias, method: __METHOD__);
            }

            $invoked->tablesUsed[$targetAlias] = $table;

            $filterQueryBuilder = $this->filterQueryBuilderFactory->create($targetAlias);

            $value = $filterValues[$key] ?? null;
            $invocation = new FilterInvocation(
                filter: $filter,
                list: $listSpecification,
                context: $contextConfig,
                value: $value
            );

            if (!$callback = $this->filterInvoker->get(
                filterType: $filter->getType(),
                contextType: $contextConfig::getContextType()
            )) {
                continue;
            }

            $event = $this->eventDispatcher->dispatch(new FilterElementInvokingEvent(
                invocation: $invocation,
                callback: $callback,
                shouldInvoke: true,
            ));

            if (!$event->shouldInvoke()) {
                continue;
            }

            $callback = $event->getCallback();

            try
            {
                $callback($invocation, $filterQueryBuilder);
            }
            catch (AbortFilteringException $e)
            {
                throw $e;
            }
            catch (FilterException $e)
            {
                $serviceId = \is_array($callback) && \is_object($callback[0] ?? null) ? $callback[0]::class : 'unknown';
                $method = \is_array($callback) ? ($callback[1] ?? 'undefined/__invoke') : '__invoke';
                $errorMethod = $e->getMethod() ?? ($serviceId . '::' . $method);

                throw new FilterException(
                    \sprintf('[FLARE] Query denied: %s', $e->getMessage()),
                    code: $e->getCode(), previous: $e, method: $errorMethod,
                    source: \sprintf('tl_flare_filter.id=%s', $filter->getSourceFilterModel()?->id ?: 'unknown'),
                );
            }

            $method = \is_array($callback) ? ($callback[1] ?? '__invoke') : '__invoke';
            $this->eventDispatcher->dispatch(new FilterElementInvokedEvent($invocation, $filterQueryBuilder, $method));

            $filterQuery = $filterQueryBuilder->build((string) ++$i);

            if (!$sql = $filterQuery->getSql())
            {
                continue;
            }

            $invoked->conditions[] = $sql;

            foreach ($filterQuery->getParams() as $k => $v) {
                $invoked->parameters[$k] = $v;
            }

            foreach ($filterQuery->getTypes() as $k => $v) {
                $invoked->types[$k] = $v;
            }
        }

        return $invoked;
    }
}