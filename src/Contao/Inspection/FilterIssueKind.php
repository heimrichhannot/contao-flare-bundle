<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Inspection;

use HeimrichHannot\FlareBundle\Contract\FilterElement\IneffectiveFilterContract;

/**
 * The kind of a filter issue the backend reports ({@see Inspector\FilterInspector}); five kinds, of which a row may
 * have several issues each. The case order is the order of the overview messages and of a row's issues and icons.
 */
enum FilterIssueKind: string
{
    /** Building or checking the filter threw an \Error (caught outside debug mode only). */
    case ProgrammingError = 'programming_error';

    /** The filter shares its alias with another filter of the list. */
    case DuplicateAlias = 'duplicate_alias';

    /** The filter cannot be created, or its conditions cannot be built with its default value. */
    case Broken = 'broken';

    /**
     * The filter restricts nothing as configured ({@see IneffectiveFilterContract}), or it is exclusive to no
     * output.
     */
    case Ineffective = 'ineffective';

    /** A hint on a valid setup that may not do what the editor expects; never fails a list. */
    case Warning = 'warning';

    /**
     * The icon of an issue of this kind that names none.
     */
    public function defaultIcon(): FilterIssueIcon
    {
        return match ($this) {
            self::ProgrammingError => FilterIssueIcon::Bug,
            self::DuplicateAlias => FilterIssueIcon::CopyX,
            self::Broken => FilterIssueIcon::BoneFracture,
            self::Ineffective => FilterIssueIcon::Engine,
            self::Warning => FilterIssueIcon::BadgeExclamationPoint,
        };
    }
}
