<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;

trait GroupsEntriesTrait
{
    /**
     * @throws FlareException
     */
    public function groupEntriesByDate(array $entries): array
    {
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
        foreach ($byDate as $date => $group)
        {
            \usort($group, static function (array $a, array $b): int {
                if (0 === $comp = $a['startTime'] <=> $b['startTime']) {
                    return $a['endTime'] <=> $b['endTime'];
                }

                return $comp;
            });

            $byDate[$date] = $group;
        }

        // Sort the dates so that the earliest date is first
        \uksort($byDate, static fn (string $a, string $b): int => $a <=> $b);

        return $byDate;
    }

    /**
     * @throws FlareException
     */
    protected function fillInitialEvents(array &$out, array $entry, \DateTime $startDate, \DateTime $endDate): void
    {
        try
        {
            $interval = new \DateInterval('P1D');
            $period = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
            foreach ($period as $date) {
                $out[$date->format('Y-m-d')][] = $entry;
            }
        }
        catch (\Exception $e)
        {
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

        if (\in_array($unit, ['week', 'weeks'], true))
        {
            $repeatInterval->d = $value * 7;
        }
        /** @mago-expect lint:no-else-clause This is the most straightforward way to handle the different units. */
        elseif ($prop)
        {
            $repeatInterval->{$prop} = $value;
        }
        /** @mago-expect lint:no-else-clause This is fine. */
        else
        {
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