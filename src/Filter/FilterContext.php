<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

/**
 * The element's product for one invocation (stage 2): the scope it was applied in and the
 * formula it produced for the value. A formula only exists once the value is known.
 */
final readonly class FilterContext
{
    public function __construct(
        public FilterScope $scope,
        public Formula     $formula,
    ) {}
}
