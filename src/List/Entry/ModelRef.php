<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Entry;

/**
 * Names the physical record of the list's main table an entry was built from: {@see EntryCollection::forTable()}
 * takes `row['id']`, which is `main.id` while the query selects `main.*`. Carries no Contao dependency.
 */
final readonly class ModelRef
{
    public function __construct(
        public string     $table,
        public int|string $id,
    ) {}
}
