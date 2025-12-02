<?php

namespace HeimrichHannot\FlareBundle\ListItemProvider;

use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use HeimrichHannot\FlareBundle\List\ListQueryBuilder;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

/**
 * Interface required for list item providers.
 */
interface ListItemProviderInterface
{
    /**
     * Fetch the total number of entries matching the given filters, ignoring pagination and sorting.
     * MUST return an integer.
     *
     * @return int Returns the total number of entries matching the given filters.
     */
    public function fetchCount(
        ListQueryBuilder        $listQueryBuilder,
        FilterContextCollection $filters,
    ): int;

    /**
     * Fetch entries from the database.
     * MUST return an array of entry rows in the order requested and within the pagination's window.
     *
     * If you need to group entries, add an SQL-less field to the respective model's dca and set its value
     * manually in the entry's row array.
     * See the use of `_flare_event_group` in {@see EventsListItemProvider::fetchEntries()} as an example.
     *
     * Calling {@see fetchIds()} with the identical parameters MUST return the same entries' IDs and
     * in the same order.
     *
     * @return array<int, array> Returns an array of associative arrays, each mapping column names to their values.
     */
    public function fetchEntries(
        ListQueryBuilder        $listQueryBuilder,
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array;

    /**
     * Fetch the IDs of entries from the database.
     * MUST return a flat array of IDs in the order requested and within the pagination's window.
     *
     * Calling {@see fetchEntries()} with the identical parameters MUST return the entries corresponding to
     * these IDs and in the same order.
     *
     * @return array<int> Returns an array of unique IDs.
     */
    public function fetchIds(
        ListQueryBuilder        $listQueryBuilder,
        FilterContextCollection $filters,
        ?SortDescriptor         $sortDescriptor = null,
        ?Paginator              $paginator = null,
    ): array;
}