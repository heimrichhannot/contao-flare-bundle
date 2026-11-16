<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Exception\FlareException;

interface ValidationLoaderInterface
{
    /**
     * The member row whose auto-item field holds exactly $autoItem, by the reader's rules: all rows the database
     * matches, without LIMIT/OFFSET, in the list's order and then `main.id`; the first strictly equal one wins.
     *
     * @return array<string, mixed>|null the row, or null if no row is strictly equal
     *
     * @throws FlareException
     */
    public function fetchEntryByAutoItem(string $autoItem): ?array;

    /**
     * One query `main.id IN (:ids)` over the list's query by the reader's rules, without LIMIT/OFFSET. An aborted
     * filter yields no rows.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>> the member rows in database order
     *
     * @throws FlareException
     */
    public function fetchEntriesByIds(array $ids): array;
}
