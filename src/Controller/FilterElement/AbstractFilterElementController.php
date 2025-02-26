<?php

namespace HeimrichHannot\FlareBundle\Controller\FilterElement;

abstract class AbstractFilterElementController
{
    public function __invoke()
    {
        // TODO: Implement __invoke() method.
    }

    public function formTypeOptions(): array
    {
        return [];
    }
}