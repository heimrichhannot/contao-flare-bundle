<?php

namespace HeimrichHannot\FlareBundle\List\Type\ItemProvider;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\List\ListFilterTrait;
use HeimrichHannot\FlareBundle\List\ListItemProvider;
use HeimrichHannot\FlareBundle\List\ListItemProviderInterface;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventsListItemProvider implements ListItemProviderInterface
{
    use ListFilterTrait;

    public function __construct(
        private readonly Connection               $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ListItemProvider         $listItemProvider,
    ) {}

    protected function getConnection(): Connection
    {
        return $this->connection;
    }

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function fetchCount(FilterContextCollection $filters): int
    {
        // TODO: Implement fetchCount() method.

        return 0;
    }

    /**
     * @throws FilterException
     */
    public function fetchEntries(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        $sortDescriptor ??= SortDescriptor::fromMap([
            'startTime' => 'ASC',
            'endTime'   => 'DESC',
        ]);

        $dto = $this->buildFilteredQuery(
            filters: $filters,
            order: $sortDescriptor?->toSql(),
        );

        if (!$dto->isAllowed())
        {
            return [];
        }

        $result = $this->connection->executeQuery($dto->getQuery(), $dto->getParams(), $dto->getTypes());

        $entries = $result->fetchAllAssociative();

        $entries = \array_combine(\array_column($entries, 'id'), $entries);

        $result->free();

        /** @var array<string, array> $byDate All entries mapped to each day on which they occur */
        $byDate = [];

        foreach ($entries as $entry)
        {
            $startTime = $entry['startTime'] ?? null;
            $endTime = $entry['endTime'] ?? $startTime;

            $startDate = DateTimeHelper::timestampToDateTime($startTime)
                ?->setTimezone(DateTimeHelper::getTimeZone())
                ->setTime(0, 0, 0);
            $endDate = DateTimeHelper::timestampToDateTime($endTime)
                ?->setTimezone(DateTimeHelper::getTimeZone())
                ->setTime(0, 0, 0);

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
                return $a['startTime'] <=> $b['startTime'];
            });

            $byDate[$date] = $entries;
        }

        // Sort the dates so that the earliest date is first
        \uksort($byDate, function ($a, $b) {
            return $a <=> $b;
        });

        // Now, we need to flatten the array and sort it by date while respecting the paginator
        $limit = $paginator?->getItemsPerPage() ?: null;
        $targetOffset = $paginator?->getOffset() ?: null;
        $currentOffset = 0;
        $entries = [];

        foreach ($byDate as $entriesOnDate)
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

                $entries[$entry['id']] = $entry;

                if ($limit && \count($entries) >= $limit) {
                    break;
                }
            }
        }

        return $entries;
    }

    protected function fillInitialEvents(array &$out, array $entry, \DateTime $startDate, \DateTime $endDate): void
    {
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
        foreach ($period as $date)
        {
            $out[$date->format('Y-m-d')][] = $entry;
        }
    }

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

    public function fetchIds(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array {
        // TODO: Implement fetchIds() method.

        return [];
    }
}