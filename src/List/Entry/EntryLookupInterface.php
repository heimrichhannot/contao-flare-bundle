<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Entry;

interface EntryLookupInterface
{
    /**
     * `mixed`, not `int|string|array`: Twig calls methods without strict_types, so a typed parameter would coerce
     * `true` to 1 before the implementation can reject it with its own message.
     *
     * @param int|string|list<int|string>|null $ids ids of the list's own table; `null` and `''` (an empty relation)
     *     count as no id — alone they return `null`, in a list they are skipped; anything else throws
     *     InvalidLookupIdError
     * @return ($ids is array ? EntryCollection : ?Entry)
     */
    public function lookup(mixed $ids): Entry|EntryCollection|null;
}
