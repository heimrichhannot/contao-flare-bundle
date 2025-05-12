<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Manager;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

readonly class FilterQueryManager
{
    public function __construct(
        private Connection               $connection,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchEntries(
        FilterContextCollection $filters,
        ?Paginator              $paginator = null,
        ?bool                   $returnIds = null
    ): array {
        $returnIds ??= false;

        [$sql, $params, $types, $allowed] = $this->buildFilteredQuery(
            filters: $filters,
            limit: $paginator?->getItemsPerPage() ?: null,
            offset: $paginator?->getOffset() ?: null,
            onlyId: $returnIds
        );

        if (!$allowed) {
            return [];
        }

        $result = $this->connection->executeQuery($sql, $params, $types);

        if ($returnIds) {
            $entries = $result->fetchFirstColumn();
        } else {
            $entries = $result->fetchAllAssociative();
            $entries = \array_combine(\array_column($entries, 'id'), $entries);
        }

        $result->free();

        return $entries;
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetchCount(FilterContextCollection $filters): int
    {
        [$sql, $params, $types, $allowed] = $this->buildFilteredQuery($filters, isCounting: true);

        if (!$allowed) {
            return 0;
        }

        $result = $this->connection->executeQuery($sql, $params, $types);

        $count = $result->fetchOne() ?: 0;

        $result->free();

        return $count;
    }

    /**
     * @throws FilterException
     */
    public function buildFilteredQuery(
        FilterContextCollection $filters,
        ?int                    $limit = null,
        ?int                    $offset = null,
        ?string                 $order = null,
        bool                    $isCounting = false,
        bool                    $onlyId = false,
    ): array {
        $combinedConditions = [];
        $combinedParameters = [];
        $combinedTypes = [];

        $table = $filters->getTable();
        $as = 'main';

        if (!Str::isValidSqlName($table)) {
            throw new FilterException(\sprintf('[FLARE] Invalid table name: %s', $table), method: __METHOD__);
        }

        $blockResult = ["SELECT 1 FROM `$table` as $as LIMIT 0", [], [], false];

        foreach ($filters as $i => $filter)
        {
            $config = $filter->getConfig();

            $service = $config->getService();
            $method = $config->getMethod() ?? '__invoke';

            if (!\method_exists($service, $method))
            {
                continue;
            }

            $filterQueryBuilder = new FilterQueryBuilder($this->connection->createExpressionBuilder(), $as);

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
                    source: \sprintf('tl_flare_filter.id=%s', $filter->getFilterModel()?->id)
                );
            }

            $event = new FilterElementInvokedEvent($filter, $filterQueryBuilder, $method);
            $this->eventDispatcher->dispatch($event, "huh.flare.filter_element.{$filter->getFilterAlias()}.invoked");

            if ($filterQueryBuilder->isBlocking())
            {
                return $blockResult;
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
        $finalSQL .= " FROM `$table` AS $as WHERE ";
        $finalSQL .= empty($combinedConditions) ? '1' : $this->connection->createExpressionBuilder()->and(...$combinedConditions);

        if (!$isCounting)
        {
            if (isset($limit)) $finalSQL .= " LIMIT $limit";
            if (isset($offset)) $finalSQL .= " OFFSET $offset";
            if (isset($order)) $finalSQL .= " ORDER BY $order";
        }

        return [$finalSQL, $combinedParameters, $combinedTypes, true];
    }
}