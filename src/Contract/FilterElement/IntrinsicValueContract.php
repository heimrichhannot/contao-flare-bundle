<?php

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Filter\FilterDefinition;

interface IntrinsicValueContract
{
    /**
     * Returns the intrinsic value (e.g. `preselect`) from the definition.
     */
    public function getIntrinsicValue(FilterDefinition $definition): mixed;
}