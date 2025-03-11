<?php

namespace HeimrichHannot\FlareBundle\Manager;

use Contao\CoreBundle\Monolog\ContaoContext;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Event\FilterElementInvokedEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

readonly class FilterQueryManager
{
    public function __construct(
        private Connection               $connection,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @noinspection PhpFullyQualifiedNameUsageInspection
     *
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function fetch(FilterContextCollection $filters): array
    {
        [$sql, $params, $types] = $this->buildFilteredQuery($filters);

        $result = $this->connection->executeQuery($sql, $params, $types);

        $entries = $result->fetchAllAssociative();

        $result->free();

        return $entries;
    }

    /**
     * @throws FilterException
     */
    public function buildFilteredQuery(FilterContextCollection $filters): array
    {
        $combinedConditions = [];
        $combinedParameters = [];
        $combinedTypes = [];

        $table = $filters->getTable();
        $as = 'main';

        if (!Str::isValidSqlName($table)) {
            throw new FilterException(\sprintf('[FLARE] Invalid table name: %s', $table), method: __METHOD__);
        }

        $blockResult = ["SELECT 1 FROM `$table` LIMIT 0", [], []];

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
                $service->{$method}($filterQueryBuilder, $filter);
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

        $finalSQL = "SELECT * FROM `$table` AS $as";
        if (!empty($combinedConditions))
        {
            $finalSQL .= ' WHERE ' . $this->connection->createExpressionBuilder()->and(...$combinedConditions);
        }

        return [$finalSQL, $combinedParameters, $combinedTypes];
    }
}