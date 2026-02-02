<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Dto\InvokeFiltersResult;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\PaginatedContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\SortableContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokingEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\FilterInvoker\FilterInvokerResolver;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\Factory\QueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Sort\SortOrder;
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
        private readonly FilterInvokerResolver     $filterInvoker,
        private readonly FilterQueryBuilderFactory $filterQueryBuilderFactory,
        private readonly QueryBuilderFactory       $queryBuilderFactory,
    ) {}

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
    ): ?QueryBuilder {
        if (!Str::isValidSqlName($table = $listSpecification->dc)) {
            throw new FilterException(
                \sprintf('[FLARE] Invalid table name: %s', $table), method: __METHOD__,
            );
        }

        $order = null;
        $limit = null;
        $offset = null;

        if ($contextConfig instanceof SortableContextInterface
            && $sortOrderSequence = $contextConfig->getSortOrderSequence())
        {
            $order = \array_map(
                static fn (SortOrder $o): array => [$o->getQualifiedColumn(), $o->getDirection()],
                $sortOrderSequence->getItems()
            );
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
            return null;
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
            return null;
        }

        $aliasesUsed = \array_unique(\array_merge(
            \array_keys($invoked->tablesUsed),
            $listQueryBuilder->getMandatoryTableAliases(),
        ));

        $queryStruct = $listQueryBuilder->build()->filterJoinAliases($aliasesUsed);

        $altSelect = match (true) {
            $isCounting => [
                \sprintf(
                    "COUNT(%s) AS %s",
                    (\count($queryStruct->getJoins()) < 1)
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

        if ($altSelect) {
            $queryStruct->setSelect($altSelect);
        }

        if ($invoked->conditions) {
            $queryStruct->setConditions($this->connection->createExpressionBuilder()->and(...$invoked->conditions));
        }

        if ($isCounting) {
            $queryStruct->setGroupBy(null);
        }

        if (!$isCounting) {
            $queryStruct->setOrderBy($order);
            $queryStruct->setLimit($limit);
            $queryStruct->setOffset($offset);
        }

        $queryStruct->setParams($invoked->parameters);
        $queryStruct->setTypes($invoked->types);

        return $this->queryBuilderFactory->create($queryStruct);
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