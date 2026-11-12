<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Exception\FlareException;

interface ValidationLoaderInterface
{
    public function fetchEntryById(int $id): ?array;

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
