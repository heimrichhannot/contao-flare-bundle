<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class FilterElementContext
{
    public function __construct(
        public ListSpecification       $list,
        public ConfiguredFilter        $filter,
        public ContextInterface        $engineContext,
        public FilterElementDescriptor $descriptor,
    ) {}
}
