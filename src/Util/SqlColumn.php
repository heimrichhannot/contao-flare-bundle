<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Util;

/**
 * A physical table column of a DCA field.
 *
 * @internal This class is not part of the public API and may change without notice.
 */
final readonly class SqlColumn
{
    /**
     * @param string $name  The physical column name.
     * @param string $field The key of the DCA field that defines the column.
     */
    public function __construct(
        public string        $name,
        public string        $field,
        public SqlColumnKind $kind,
    ) {}
}
