<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Filter\FilterScope;

/**
 * Optional port for elements that restrict a date range control: the bounds for the widget's
 * min/max attributes (SPEC_FILTER_FORMS.md §3.5). Without it the control renders unbounded.
 *
 * @api
 */
interface DateBoundsContract
{
    public function getDateBounds(FilterScope $scope): DateBounds;
}
