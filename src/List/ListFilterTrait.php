<?php

namespace HeimrichHannot\FlareBundle\List;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Dto\FilteredQueryDto;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait ListFilterTrait
{
    abstract function getConnection(): Connection;
    abstract function getEventDispatcher(): EventDispatcherInterface;

    /**
     * @throws FilterException
     */
    public function getQuotTable(FilterContextCollection $filters): string
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
        FilterContextCollection $filters,
        ?string                 $order = null,
        ?int                    $limit = null,
        ?int                    $offset = null,
        bool                    $isCounting = false,
        bool                    $onlyId = false,
    ): FilteredQueryDto {
        $combinedConditions = [];
        $combinedParameters = [];
        $combinedTypes = [];

        $table = $this->getQuotTable($filters);
        $as = $this->getConnection()->quoteIdentifier('main');

        foreach ($filters as $i => $filter)
        {
            $filterQueryBuilder = new FilterQueryBuilder($this->getConnection()->createExpressionBuilder(), $as);

            if (!$this->invokeFilter($filterQueryBuilder, $filter))
            {
                continue;
            }

            if ($filterQueryBuilder->isBlocking())
            {
                return FilteredQueryDto::block();
            }

            [$sql, $params, $types] = $filterQueryBuilder->buildQuery((string) $i);

            if (empty($sql))
            {
                continue;
            }

            $combinedConditions[] = $sql;
            $combinedParameters = \array_merge($combinedParameters, $params);
            $combinedTypes = \array_merge($combinedTypes, $types);
        }

        $finalSQL = match (true) {
            $isCounting => "SELECT COUNT(*) AS count",
            $onlyId => "SELECT $as.id AS id",
            default => "SELECT *",
        };
        $finalSQL .= " FROM $table AS $as WHERE ";
        $finalSQL .= empty($combinedConditions) ? '1'
            : $this->connection->createExpressionBuilder()->and(...$combinedConditions);

        if (!$isCounting)
        {
            if (isset($order))  $finalSQL .= " ORDER BY $order";
            if (isset($limit))  $finalSQL .= " LIMIT $limit";
            if (isset($offset)) $finalSQL .= " OFFSET $offset";
        }

        return new FilteredQueryDto($finalSQL, $combinedParameters, $combinedTypes, true);
    }

    /**
     * @throws FilterException
     */
    public function invokeFilter(
        FilterQueryBuilder $filterQueryBuilder,
        FilterContext      $filter,
        ?bool              $dispatchEvent = null,
    ): bool {
        $config = $filter->getConfig();

        $service = $config->getService();
        $method = $config->getMethod() ?? '__invoke';

        if (!\method_exists($service, $method)) {
            return false;
        }

        try
        {
            $service->{$method}($filter, $filterQueryBuilder);
        }
        catch (FilterException $e)
        {
            $method = $e->getMethod() ?? ($service::class . '::' . $method);

            throw new FilterException(
                \sprintf('[FLARE] Query denied: %s', $e->getMessage()),
                code: $e->getCode(), previous: $e, method: $method,
                source: \sprintf('tl_flare_filter.id=%s', $filter->getFilterModel()?->id),
            );
        }

        if ($dispatchEvent ?? true)
        {
            $event = new FilterElementInvokedEvent($filter, $filterQueryBuilder, $method);
            $this->getEventDispatcher()->dispatch(
                $event,
                "huh.flare.filter_element.{$filter->getFilterAlias()}.invoked"
            );
        }

        return true;
    }
}