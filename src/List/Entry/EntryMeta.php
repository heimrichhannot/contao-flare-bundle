<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Entry;

/**
 * Metadata of an entry. New metadata goes here, never into the reserved names of {@see Entry}.
 */
final readonly class EntryMeta
{
    public function __construct(
        public ?ModelRef $modelRef = null,
    ) {}
}
