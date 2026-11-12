<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Entry;

interface EntryLookupInterface
{
    /**
     * `mixed`, not `int|string|array`: Twig calls methods without strict_types, so a typed parameter would coerce
     * `true` to 1 and turn `null` into a TypeError before the implementation can reject it with its own message.
     *
     * @param int|string|list<int|string> $ids ids of the list's own table; anything else throws InvalidLookupIdError
     * @return ($ids is array ? EntryCollection : ?Entry)
     */
    public function lookup(mixed $ids): Entry|EntryCollection|null;
}
