<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class FilterInvocation
{
    public function __construct(
        public FilterDefinition       $definition,
        public ListSpecification      $spec,
        public ContextConfigInterface $contextConfig,
        public mixed                  $value = null,
    ) {}
}