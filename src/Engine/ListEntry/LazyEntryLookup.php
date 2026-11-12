<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\ListEntry;

use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;

/**
 * Builds its lookup on the first call, once: a view that never looks up builds no loader or collector for it.
 */
final class LazyEntryLookup implements EntryLookupInterface
{
    private ?EntryLookupInterface $lookup = null;

    /**
     * @param \Closure(): EntryLookupInterface $create
     */
    public function __construct(
        private readonly \Closure $create,
    ) {}

    public function lookup(mixed $ids): Entry|EntryCollection|null
    {
        return ($this->lookup ??= ($this->create)())->lookup($ids);
    }
}
