<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Inspection;

/**
 * The lucide icons a filter issue can be shown with, one partial each in `templates/backend/filter_icon/`, named by
 * the case value. Each kind has its default ({@see FilterIssueKind::defaultIcon()}); an issue may name another.
 */
enum FilterIssueIcon: string
{
    case Bug = 'bug';
    case CopyX = 'copy-x';
    case BoneFracture = 'bone-fracture';
    case Engine = 'engine';
    case BadgeExclamationPoint = 'badge-exclamation-point';
    case Opacity = 'opacity';
}
