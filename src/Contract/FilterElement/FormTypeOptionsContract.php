<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;

interface FormTypeOptionsContract
{
    /**
     * @throws FlareException
     */
    public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void;
}