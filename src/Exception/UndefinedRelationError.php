<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * A relation was requested that the table does not define, or for a table without a model class. This is a
 * programming error: the template asks for a column Contao knows no relation for.
 */
final class UndefinedRelationError extends \Error
{
    public function __construct(
        public readonly string $table,
        public readonly string $column,
        string $reason = 'has no relation',
    ) {
        parent::__construct(\sprintf('The column "%s.%s" %s.', $table, $column, $reason));
    }
}
