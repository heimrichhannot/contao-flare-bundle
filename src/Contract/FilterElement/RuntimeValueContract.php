<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

interface RuntimeValueContract
{
    /**
     * Processes and returns a normalized filter value that has been determined at execution runtime,
     *   as opposed to values defined through intrinsic configuration, i.e., in the backend.
     *
     * The value parameter might have been resolved by a Symfony form component or set within a Twig template.
     *
     * @param mixed $value The value to filter on, which has been determined at runtime.
     * @return mixed The processed value, which will be passed to the filter method upon invocation, where it can
     *   be accessed through `$invocation->getValue()`.
     */
    public function processRuntimeValue(mixed $value, ListSpecification $list, FilterDefinition $filter): mixed;
}