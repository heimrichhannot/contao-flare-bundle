<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Entry;

/**
 * Names the record of a DC table an entry was built from; carries no Contao dependency.
 */
final readonly class ModelRef
{
    public function __construct(
        public string     $table,
        public int|string $id,
    ) {}
}
