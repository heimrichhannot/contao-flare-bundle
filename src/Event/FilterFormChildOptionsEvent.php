<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\Event;

class FilterFormChildOptionsEvent extends Event
{
    public function __construct(
        public readonly ListSpecification $listSpecification,
        public readonly FilterDefinition  $filterDefinition,
        public readonly ?string           $parentFormName,
        public readonly string            $formName,
        public array                      $options,
    ) {}
}