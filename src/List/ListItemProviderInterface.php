<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

/**
 * Interface required for list item providers.
 */
interface ListItemProviderInterface
{
    /**
     * @return int Returns the total number of entries matching the given filters.
     */
    public function fetchCount(FilterContextCollection $filters): int;

    /**
     * Fetch entries from the database.
     * MUST return a flat array of entries in the order requested and within the pagination's window.
     *
     * If you need to group entries, add an SQL-less field to the respective model's dca and set its value
     * manually in the entry's row array.
     * See {@see \HeimrichHannot\FlareBundle\List\Type\ItemProvider\EventsListItemProvider} as an example.
     *
     * @return array<int, array> Returns an array of associative arrays, each mapping column names to their values.
     */
    public function fetchEntries(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array;

    /**
     * Return the one entity's row of the given id.
     *
     * @return array<string, mixed>|null Returns an associative array mapping column names to their values, or null if not found.
     */
    public function fetchEntry(FilterContextCollection $filters, int $id): ?array;

    /**
     * Fetch the IDs of entries from the database.
     * MUST return a flat array of IDs in the order requested and within the pagination's window.
     *
     * @return array<int> Returns an array of IDs.
     */
    public function fetchIds(
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array;
}