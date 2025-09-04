<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Dto\FilterInvocationDto;
use HeimrichHannot\FlareBundle\Dto\ParameterizedSqlQuery;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokingEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\Descriptor\ListTypeDescriptor;
use HeimrichHannot\FlareBundle\Registry\ListTypeRegistry;
use HeimrichHannot\FlareBundle\Util\CallbackHelper;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
        private readonly FlareCallbackManager      $callbackManager,
        private readonly ListTypeRegistry          $listTypeRegistry,
    ) {}

    /**
     * @throws FlareException
     */
    public function prepare(ListModel $listModel, ?bool $noCache = null): ListQueryBuilder
    {
        $doCache = !$noCache;

        if ($doCache && $hit = $this->prepCache[$listModel->id] ?? null) {
            return clone $hit;
        }

        /** @var ListTypeDescriptor $type */
        if (!$type = $this->listTypeRegistry->get($listModel->type)) {
            throw new FlareException(\sprintf('No list type registered for type "%s".', $listModel->type), method: __METHOD__);
        }

        if (!$mainTable = $listModel->dc ?? $type->getDataContainer()) {
            throw new FlareException('No data container table set.', method: __METHOD__);
        }

        $builder = new ListQueryBuilder(
            connection: $this->connection,
            mainTable: $mainTable,
            mainAlias: self::ALIAS_MAIN,
        );

        $builder->select('*', of: self::ALIAS_MAIN, allowAsterisk: true);
        $builder->groupBy('id', self::ALIAS_MAIN);

        $callbacks = $this->callbackManager->getListCallbacks($listModel->type, 'query.configure');

        CallbackHelper::call($callbacks, [], [
            ListModel::class => $listModel,
            ListQueryBuilder::class => $builder,
        ]);

        $builder->select('id', of: self::ALIAS_MAIN, as: 'id');

        if ($doCache) {
            $this->prepCache[$listModel->id] = clone $builder;
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

        if (\is_array($select) && !empty($select))
        {
            $select = \array_unique(\array_map(function ($column) {
                return $this->connection->quoteIdentifier(self::ALIAS_MAIN . '.' . $column);
            }, $select));
        }

        if (\is_array($select) && empty($select))
        {
            return ParameterizedSqlQuery::noResult();
        }

        $aliasesUsed = \array_unique(\array_merge(
            \array_keys($invoked->tablesUsed),
            $listQueryBuilder->getMapTableAliasMandatory(),
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
            conditions: empty($invoked->conditions)
                ? '1 = 1'
                : $this->connection->createExpressionBuilder()->and(...$invoked->conditions),
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
            $sql = $filterQuery->getSql();

            if (empty($sql))
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
            $event = new FilterElementInvokingEvent($filter, $callback, true);

            $this->eventDispatcher->dispatch(
                $event,
                "flare.filter_element.{$filter->getFilterAlias()}.invoking"
            );

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
            $event = new FilterElementInvokedEvent($filter, $filterQueryBuilder, $method);
            $this->eventDispatcher->dispatch(
                $event,
                "flare.filter_element.{$filter->getFilterAlias()}.invoked"
            );
        }

        return self::FILTER_OK;
    }
}