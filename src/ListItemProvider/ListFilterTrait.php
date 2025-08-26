<?php

namespace HeimrichHannot\FlareBundle\ListItemProvider;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Dto\FilteredQueryDto;
use HeimrichHannot\FlareBundle\Dto\FilterInvocationDto;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokingEvent;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\List\ListQuery;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait ListFilterTrait
{
    public const FILTER_OK = 0;
    public const FILTER_SKIP = 1;
    public const FILTER_FAIL = 2;
    public const ALIAS_MAIN = 'main';

    abstract public function getConnection(): Connection;

    abstract public function getEventDispatcher(): EventDispatcherInterface;

    /**
     * @throws FilterException
     */
    public function quoteTable(FilterContextCollection $filters): string
    {
        if (!Str::isValidSqlName($filters->getTable())) {
            throw new FilterException(
                \sprintf('[FLARE] Invalid table name: %s', $filters->getTable()), method: __METHOD__,
            );
        }

        return $this->getConnection()->quoteIdentifier($filters->getTable());
    }

    /**
     * @throws FilterException
     */
    public function buildFilteredQuery(
        ListQuery               $listQuery,
        FilterContextCollection $filters,
        ?string                 $order = null,
        ?int                    $limit = null,
        ?int                    $offset = null,
        bool                    $isCounting = false,
        bool                    $onlyId = false,
        ?array                  $select = null,
    ): FilteredQueryDto {
        $qTable = $this->quoteTable($filters);  // quote early for value check

        try
        {
            $invoked = $this->invokeFilters($filters);
        }
        catch (AbortFilteringException)
        {
            return FilteredQueryDto::block();
        }

        if (\is_array($select) && !empty($select))
        {
            $select = \array_unique(\array_map(function ($column) {
                return $this->getConnection()->quoteIdentifier(self::ALIAS_MAIN . '.' . $column);
            }, $select));
        }

        if (\is_array($select) && empty($select))
        {
            return FilteredQueryDto::block();
        }

        $finalSQL = match (true) {
            $isCounting => "SELECT COUNT(*) AS count",
            $onlyId => "SELECT " . $this->getConnection()->quoteIdentifier(self::ALIAS_MAIN . '.id') . " AS id",
            \is_array($select) => "SELECT " . \implode(',', $select),
            default => "SELECT *",
        };

        // todo: add support for joins

        $finalSQL .= \sprintf(" FROM $qTable AS %s WHERE ", self::ALIAS_MAIN);
        $finalSQL .= empty($invoked->conditions) ? '1'
            : $this->connection->createExpressionBuilder()->and(...$invoked->conditions);

        if (!$isCounting)
        {
            if (isset($order))  $finalSQL .= " ORDER BY $order";
            if (isset($limit))  $finalSQL .= " LIMIT $limit";
            if (isset($offset)) $finalSQL .= " OFFSET $offset";
        }

        return new FilteredQueryDto($finalSQL, $invoked->parameters, $invoked->types, true);
    }

    /**
     * @throws FilterException
     * @throws AbortFilteringException
     */
    public function invokeFilters(FilterContextCollection $filters): FilterInvocationDto
    {
        $invoked = new FilterInvocationDto();

        $asMain = 'main';

        foreach ($filters as $i => $filter)
        {
            $filterQueryBuilder = new FilterQueryBuilder($this->getConnection(), $asMain);

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

            $this->getEventDispatcher()->dispatch(
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
            $method = $e->getMethod() ?? ($service::class . '::' . $method);

            throw new FilterException(
                \sprintf('[FLARE] Query denied: %s', $e->getMessage()),
                code: $e->getCode(), previous: $e, method: $method,
                source: \sprintf('tl_flare_filter.id=%s', $filter->getFilterModel()?->id ?: 'unknown'),
            );
        }

        if ($dispatchEvent ?? true)
        {
            $event = new FilterElementInvokedEvent($filter, $filterQueryBuilder, $method);
            $this->getEventDispatcher()->dispatch(
                $event,
                "flare.filter_element.{$filter->getFilterAlias()}.invoked"
            );
        }

        return self::FILTER_OK;
    }
}