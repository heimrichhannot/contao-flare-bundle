<?php

namespace HeimrichHannot\FlareBundle\List\Type\ItemProvider;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\NotImplementedException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\List\AbstractListItemProvider;
use HeimrichHannot\FlareBundle\List\ListFilterTrait;
use HeimrichHannot\FlareBundle\List\ListItemProvider;
use HeimrichHannot\FlareBundle\Manager\FilterContextManager;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventsListItemProvider extends AbstractListItemProvider
{
    use ListFilterTrait;

    public function __construct(
        private readonly Connection               $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterContextManager     $filterContextManager,
        private readonly ListItemProvider         $listItemProvider,
    ) {
        parent::__construct($this->filterContextManager);
    }

    protected function getConnection(): Connection
    {
        return $this->connection;
    }

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    /**
     * @throws FilterException
     * @throws FlareException
     */
    public function fetchCount(FilterContextCollection $filters): int
    {
        $byDate = $this->fetchEntriesGrouped(
            filters: $filters,
            reduceSelect: true,
        );

        $count = 0;
        foreach ($byDate as $entriesOnDate)
        {
            $count += \count($entriesOnDate);
        }

        return $count;
    }

    /**
     * @throws FilterException
     * @throws FlareException
     */
    public function fetchEntries(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        $byDate = $this->fetchEntriesGrouped(
            filters: $filters,
            sortDescriptor: $sortDescriptor,
        );

        $table = $filters->getTable();

        // Now, we need to flatten the array and sort it by date while respecting the paginator
        $limit = $paginator?->getItemsPerPage() ?: null;
        $targetOffset = \max(0, ($paginator?->getFirstItemNumber() ?? 1) - 1);
        $currentOffset = 0;
        $entries = [];

        foreach ($byDate as $date => $entriesOnDate)
        {
            if ($limit && \count($entries) >= $limit) {
                break;
            }

            // If we have a target offset, we need to skip the entries until we reach it.
            // If there are more entries on the current date than the target offset, we can skip all of them.
            if ($targetOffset && $currentOffset + \count($entriesOnDate) <= $targetOffset) {
                $currentOffset += \count($entriesOnDate);
                continue;
            }

            // Otherwise, we need to skip the entries one by one until we reach the target offset.
            // This way, each entry appears on exactly one page only.
            foreach ($entriesOnDate as $entry)
            {
                if ($targetOffset && $currentOffset < $targetOffset) {
                    $currentOffset++;
                    continue;
                }

                // Group the entry by date by adding a new field to the entry (as defined in the dca)
                $entry['_flare_event_group'] = $date;

                // Add the entry to the list
                $entries[] = $entry;

                $this->entryCache["$table.{$entry['id']}"] ??= $entry;

                if ($limit && \count($entries) >= $limit) {
                    break;
                }
            }
        }

        return $entries;
    }

    /**
     * @throws FilterException
     * @throws FlareException
     */
    public function fetchIds(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        if ($paginator) {
            throw new NotImplementedException('Pagination is not yet supported for fetching IDs of an events list.', method: __METHOD__);
        }

        $byDate = $this->fetchEntriesGrouped(
            filters: $filters,
            sortDescriptor: $sortDescriptor,
            reduceSelect: true,
        );

        $ids = [];
        foreach ($byDate as $entriesOnDate) {
            foreach ($entriesOnDate as $entry) {
                if (!empty($entry['id'])) {
                    $ids[] = $entry['id'];
                }
            }
        }

        return \array_unique($ids);
    }

    /**
     * @return array<string, array> All entries mapped to each day on which they occur.
     *
     * @throws FilterException
     * @throws FlareException
     */
    protected function fetchEntriesGrouped(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?bool                   $reduceSelect = null,
    ): array {
        $sortDescriptor ??= SortDescriptor::fromMap([
            'startTime' => 'ASC',
            'endTime'   => 'DESC',
        ]);

        $dto = $this->buildFilteredQuery(
            filters: $filters,
            order: $sortDescriptor?->toSql(),
            select: $reduceSelect ? ['id', 'startTime', 'endTime', 'repeatEach', 'repeatEnd'] : null,
        );

        if (!$dto->isAllowed())
        {
            return [];
        }

        try
        {
            $result = $this->connection->executeQuery($dto->getQuery(), $dto->getParams(), $dto->getTypes());

            $entries = $result->fetchAllAssociative();
        }
        catch (\Throwable $exception)
        {
            throw new FlareException($exception->getMessage(), $exception->getCode(), $exception, method: __METHOD__);
        }

        $result->free();

        /** @var array<string, array> $byDate All entries mapped to each day on which they occur */
        $byDate = [];

        foreach ($entries as $entry)
        {
            $startTime = $entry['startTime'] ?? null;
            $endTime = $entry['endTime'] ?? $startTime;

            $startDate = DateTimeHelper::timestampToDateTime($startTime)?->setTime(0, 0, 0);
            $endDate = DateTimeHelper::timestampToDateTime($endTime)?->setTime(0, 0, 0);

            if (!$startDate || !$endDate) {
                continue;
            }

            // Each entry can fall onto multiple dates, either when it spans multiple dates or is a recurring event.
            // First, handle the initial occurrence of each event:
            $this->fillInitialEvents($byDate, $entry, $startDate, $endDate);
            // Second, handle recurring events:
            $this->fillRecurringEvents($byDate, $entry, $startDate, $endDate);
        }

        // Sort the entries of each date by start time
        foreach ($byDate as $date => $entries)
        {
            \usort($entries, function ($a, $b) {
                if (0 === $comp = $a['startTime'] <=> $b['startTime']) {
                    return $a['endTime'] <=> $b['endTime'];
                }

                return $comp;
            });

            $byDate[$date] = $entries;
        }

        // Sort the dates so that the earliest date is first
        \uksort($byDate, function ($a, $b) {
            return $a <=> $b;
        });

        return $byDate;
    }

    /**
     * @throws FlareException
     */
    protected function fillInitialEvents(array &$out, array $entry, \DateTime $startDate, \DateTime $endDate): void
    {
        try {
            $interval = new \DateInterval('P1D');
            $period = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
            foreach ($period as $date)
            {
                $out[$date->format('Y-m-d')][] = $entry;
            }
        } catch (\Exception $e) {
            throw new FlareException($e->getMessage(), $e->getCode(), $e, method: __METHOD__);
        }
    }

    /**
     * @throws FlareException
     */
    protected function fillRecurringEvents(array &$out, array $entry, \DateTime $startDate, \DateTime $endDate): void
    {
        $repeat = StringUtil::deserialize($entry['repeatEach'] ?? null, true);

        if (!$repeat || !isset($repeat['unit'], $repeat['value']) || $repeat['value'] < 1) {
            return;
        }

        $timeStr = '+ ' . $repeat['value'] . ' ' . $repeat['unit'];

        $value = (int) $repeat['value'];
        $unit = \strtolower($repeat['unit']);

        $repeatInterval = new \DateInterval('P0Y0M0DT0H0M0S');

        $prop = match ($unit) {
            'year', 'years' => 'y',
            'month', 'months' => 'm',
            'day', 'days' => 'd',
            'hour', 'hours' => 'h',
            'minute', 'minutes' => 'i',
            'second', 'seconds' => 's',
            default => null,
        };

        if (\in_array($unit, ['week', 'weeks'], true)) {
            $repeatInterval->d = $value * 7;
        } elseif ($prop) {
            $repeatInterval->{$prop} = $value;
        } else {
            return;
        }

        try
        {
            $repeatDate = clone $startDate;
            $repeatDate->modify($timeStr);

            $repeatEnd = (DateTimeHelper::timestampToDateTime($entry['repeatEnd'] ?? null)
                ?->setTimezone(DateTimeHelper::getTimeZone())
                ->setTime(0, 0, 0)
            ) ?: clone $endDate;

            while ($repeatDate <= $repeatEnd)
            {
                $out[$repeatDate->format('Y-m-d')][] = $entry;

                $repeatDate->add($repeatInterval);
            }
        }
        catch (\Exception $e)
        {
            throw new FlareException($e->getMessage(), $e->getCode(), $e, method: __METHOD__);
        }
    }
}