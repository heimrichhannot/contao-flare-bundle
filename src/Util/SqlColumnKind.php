<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Util;

/**
 * The coarse kind of a column's SQL definition.
 *
 * @internal This enum is not part of the public API and may change without notice.
 */
enum SqlColumnKind
{
    case Integer;
    case Date;
    case Other;
}
