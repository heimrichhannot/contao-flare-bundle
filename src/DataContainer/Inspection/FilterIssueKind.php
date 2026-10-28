<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataContainer\Inspection;

use HeimrichHannot\FlareBundle\Contract\FilterElement\IneffectiveFilterContract;

/**
 * The kind of a filter issue the backend reports ({@see FilterInspector}). The case order is the order of the
 * overview messages and the row icons.
 *
 * @internal
 */
enum FilterIssueKind: string
{
    /** Building or checking the filter threw an \Error (caught outside debug mode only). */
    case ProgrammingError = 'programming_error';

    /** The filter shares its alias with another filter of the list. */
    case DuplicateAlias = 'duplicate_alias';

    /** The filter cannot be created, or its conditions cannot be built with its default value. */
    case Broken = 'broken';

    /** The filter restricts nothing as configured ({@see IneffectiveFilterContract}). */
    case Ineffective = 'ineffective';
}
