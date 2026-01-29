<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Context\ContextConfigInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class FilterInvocation
{
    public function __construct(
        public FilterDefinition       $filter,
        public ListSpecification      $list,
        public ContextConfigInterface $context,
        public mixed                  $value = null,
    ) {}

    public function getFilterDefinition(): FilterDefinition
    {
        return $this->filter;
    }

    public function getListSpecification(): ListSpecification
    {
        return $this->list;
    }

    public function getContextConfig(): ContextConfigInterface
    {
        return $this->context;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}