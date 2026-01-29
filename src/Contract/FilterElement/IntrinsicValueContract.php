<?php

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

interface IntrinsicValueContract
{
    /**
     * Returns the intrinsic value (e.g. `preselect`) from the definition.
     */
    public function getIntrinsicValue(ListSpecification $list, FilterDefinition $filter): mixed;
}