<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Projector\AggregationProjector;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\GroupsEntriesTrait;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType\EventsListType;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class EventsAggregationProjector extends AggregationProjector
{
    use GroupsEntriesTrait;

    public function supports(ListSpecification $spec, ContextInterface $context): bool
    {
        return $spec->type === EventsListType::TYPE && $context instanceof AggregationContext;
    }

    public function priority(ListSpecification $spec, ContextInterface $context): int
    {
        return 100;
    }

    protected function createListQueryConfig(
        ListSpecification  $spec,
        AggregationContext $config,
        ?array             $filterValues = null,
    ): ListQueryConfig {
        $queryConfig = parent::createListQueryConfig($spec, $config, $filterValues);

        return $queryConfig->with(
            isCounting: true,
            attributes: [
                ...$queryConfig->attributes,
                'ContaoCalendar_overrideSelect' => ['id', 'startTime', 'endTime', 'repeatEach', 'repeatEnd'],
            ],
        );
    }

    public function fetchCount(ListQueryConfig $queryConfig): int
    {
        try
        {
            $qb = $this->createQueryBuilder($queryConfig);

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