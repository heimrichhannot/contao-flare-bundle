<?php

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;

interface FormTypeOptionsContract
{
    /**
     * @throws FilterException
     */
    public function onFormTypeOptionsEvent(FilterElementFormTypeOptionsEvent $event): void;
}