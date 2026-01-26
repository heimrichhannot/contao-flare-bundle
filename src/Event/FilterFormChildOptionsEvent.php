<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use Symfony\Contracts\EventDispatcher\Event;

class FilterFormChildOptionsEvent extends Event
{
    public function __construct(
        public readonly ListDefinition   $listDefinition,
        public readonly FilterDefinition $filterDefinition,
        public readonly ?string          $parentFormName,
        public readonly string           $formName,
        public array                     $options,
    ) {}
}