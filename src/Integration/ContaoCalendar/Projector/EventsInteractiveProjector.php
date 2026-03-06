<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\Projector;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\Interface\PaginatedContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Engine\Projector\InteractiveProjector;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\GroupsEntriesTrait;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType\EventsListType;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\View\InteractiveEventsView;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Query\ListQueryConfig;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\FormInterface;

class EventsInteractiveProjector extends InteractiveProjector
{
    use GroupsEntriesTrait;

    public function supports(ListSpecification $list, ContextInterface $context): bool
    {
        return $list->type === EventsListType::TYPE && $context instanceof InteractiveContext;
    }

    public function priority(ListSpecification $list, ContextInterface $context): int
    {
        return 100;
    }

    protected function createView(
        \Closure $fetchEntries,
        FormInterface $form,
        Paginator $paginator,
        \Closure $readerUrlGenerator,
        string $table,
        int $totalItems,
    ): InteractiveEventsView {
        return new InteractiveEventsView(
            fetchEntries: $fetchEntries,
            form: $form,
            paginator: $paginator,
            readerUrlGenerator: $readerUrlGenerator,
            table: $table,
            totalItems: $totalItems,
        );
    }

    public function fetchEntries(ListQueryConfig $queryConfig): array
    {
        $allEntriesConfig = $queryConfig->with(
            attributes: [
                ...$queryConfig->attributes,
                'ContaoCalendar_doNotPaginate' => true,
            ],
        );

        $qb = $this->createQueryBuilder($allEntriesConfig);

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
            if ($limit && count($out) >= $limit) {
                break;
            }

            if ($targetOffset && $currentOffset + count($entriesOnDate) <= $targetOffset) {
                $currentOffset += count($entriesOnDate);
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

                if ($limit && count($out) >= $limit) {
                    break;
                }
            }
        }

        return $out;
    }
}