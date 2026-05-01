<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

interface IntrinsicValueContract
{
    /**
     * Returns an intrinsic value that has been defined on the filter (i.e., in the backend, e.g. `preselect`).
     *
     * This method is called when the filter is intrinsic, and no runtime value was provided.
     * The resulting value is not passed through {@see RuntimeValueContract::processRuntimeValue()}, provided that
     *   the filter implements {@see RuntimeValueContract}.
     *
     * @return mixed Any intrinsic value of which the FilterElement's invokers know how to interpret. Will be accessible
     *   through `$invocation->getValue()` from the invoker methods.
     */
    public function getIntrinsicValue(ListSpecification $list, ConfiguredFilter $filter): mixed;
}