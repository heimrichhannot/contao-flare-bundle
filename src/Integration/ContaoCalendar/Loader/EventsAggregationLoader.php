<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Loader;

use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\GroupsEntriesTrait;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;

readonly class EventsAggregationLoader implements AggregationLoaderInterface
{
    use GroupsEntriesTrait;

    public function __construct(
        private AggregationLoaderConfig $config,
        private ListQueryDirector       $listQueryDirector,
    ) {}

    /**
     * @throws FlareException
     */
    public function fetchCount(): int
    {
        try
        {
            $queryConfig = new ListQueryConfig(
                list: $this->config->list,
                context: $this->config->context,
                filterValues: $this->config->filterValues,
                isCounting: true,
                attributes: [
                    'ContaoCalendar_overrideSelect' => ['id', 'startTime', 'endTime', 'repeatEach', 'repeatEnd'],
                ],
            );

            $qb = $this->listQueryDirector->createQueryBuilder($queryConfig);

            if (!$qb) {
                return 0;
            }

            $result = $qb->executeQuery();

            $entries = $result->fetchAllAssociative();

            $result->free();

            $byDate = $this->groupEntriesByDate($entries);

            return \array_reduce(
                $byDate,
                static fn (int $carry, array $entriesOnDate): int => $carry + \count($entriesOnDate),
                0
            );
        }
        catch (FlareException $e)
        {
            throw $e;
        }
        catch (\Throwable $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e, source: __METHOD__);
        }
    }
}