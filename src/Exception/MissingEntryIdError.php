<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * A row of a DC table has no `id`, e.g. because the select was replaced.
 */
final class MissingEntryIdError extends \Error
{
    public function __construct(public readonly string $table, public readonly int $index)
    {
        parent::__construct(\sprintf(
            'The row %d of the table "%s" has no non-empty "id" column, so no model reference can be built.',
            $index,
            $table,
        ));
    }
}
