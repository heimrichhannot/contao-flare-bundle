<?php

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;

interface FormTypeOptionsContract
{
    /**
     * @throws FlareException
     */
    public function onFormTypeOptionsEvent(FilterElementFormTypeOptionsEvent $event): void;
}