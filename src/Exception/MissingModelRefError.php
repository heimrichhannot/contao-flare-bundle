<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * A record was requested for an entry that carries no model reference. This is a programming error: only entries
 * built by {@see \HeimrichHannot\FlareBundle\List\Entry\EntryCollection::forTable()} name a record.
 */
final class MissingModelRefError extends \Error
{
    public function __construct()
    {
        parent::__construct(
            'The entry has no modelRef, so it names no record of a DC table. Entries of a DC table are built with '
            . 'EntryCollection::forTable().',
        );
    }
}
