<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterContextCollection;
use Symfony\Contracts\EventDispatcher\Event;

class FilterFormChildOptionsEvent extends Event
{
    public function __construct(
        public readonly FilterContext           $filterContext,
        public readonly FilterContextCollection $filterContextCollection,
        public readonly ?string                 $parentFormName,
        public readonly string                  $formName,
        public array                            $options,
    ) {}
}