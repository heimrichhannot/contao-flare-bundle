<?php

namespace HeimrichHannot\FlareBundle\Util;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Filter;

trait CreatesFilterExceptionTrait
{
    protected function createFilterException(
        FilterException $e,
        Filter          $filter,
        string          $fallbackMethod
    ): FilterException {
        $errorMethod = $e->getMethod() ?: $fallbackMethod;

        return new FilterException(
            \sprintf('[FLARE] Query denied: %s / Callback: %s', $e->getMessage(), $errorMethod),
            code: $e->getCode(), previous: $e, method: $errorMethod,
            source: $filter->source ?: 'filter inlined',
        );
    }
}
