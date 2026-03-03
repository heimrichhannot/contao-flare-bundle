<?php

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;

interface FormTypeOptionsContract
{
    /**
     * @throws FilterException
     */
    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array;
}