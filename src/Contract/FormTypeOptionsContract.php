<?php

namespace HeimrichHannot\FlareBundle\Contract;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;

interface FormTypeOptionsContract
{
    /**
     * @throws FilterException
     */
    public function getFormTypeOptions(FilterContext $context): array;
}