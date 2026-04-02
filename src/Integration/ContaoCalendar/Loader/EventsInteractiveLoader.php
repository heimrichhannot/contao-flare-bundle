<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Loader;

use HeimrichHannot\FlareBundle\Engine\Context\Interface\PaginatedContextInterface;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderInterface;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\GroupsEntriesTrait;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;

readonly class EventsInteractiveLoader implements InteractiveLoaderInterface
{
    use GroupsEntriesTrait;

    public function __construct(
        private InteractiveLoaderConfig $config,
        private ListQueryDirector       $listQueryDirector,
    ) {}

    public function fetchEntries(): array
    {
        $queryConfig = new ListQueryConfig(
            list: $this->config->list,
            context: $this->config->context,
            filterValues: $this->config->filterValues,
        );

        $allEntriesConfig = $queryConfig->with(
            attributes: [
                ...$queryConfig->attributes,
                'ContaoCalendar_doNotPaginate' => true,
            ],
        );

        $qb = $this->listQueryDirector->createQueryBuilder($allEntriesConfig);

        if (!$qb) {
            return [];
        }

        $result = $qb->executeQuery();
        $entries = $result->fetchAllAssociative();
        $result->free();

        $byDate = $this->groupEntriesByDate($entries);

        $paginatorConfig = $queryConfig->context instanceof PaginatedContextInterface
            ? $queryConfig->context->getPaginatorConfig()
            : null;

        $limit = $paginatorConfig?->getItemsPerPage() ?: null;
        $targetOffset = \max(0, ($paginatorConfig?->getFirstItemNumber() ?? 1) - 1);
        $currentOffset = 0;
        $out = [];

        foreach ($byDate as $date => $entriesOnDate)
        {
            if ($limit && \count($out) >= $limit) {
                break;
            }

            if ($targetOffset && $currentOffset + \count($entriesOnDate) <= $targetOffset) {
                $currentOffset += \count($entriesOnDate);
                continue;
            }

            foreach ($entriesOnDate as $entry)
            {
                if ($targetOffset && $currentOffset < $targetOffset) {
                    $currentOffset++;
                    continue;
                }

                $entry['_flare_event_group'] = $date;
                $out[] = $entry;

                if ($limit && \count($out) >= $limit) {
                    break;
                }
            }
        }

        return $out;
    }
}