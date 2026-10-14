<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class FilterInvocation
{
    public function __construct(
        public ConfiguredFilter  $filter,
        public ListSpecification $list,
        public ContextInterface  $context,
        public mixed             $value = null,
    ) {}

    public function getConfiguredFilter(): ConfiguredFilter
    {
        return $this->filter;
    }

    public function getListSpecification(): ListSpecification
    {
        return $this->list;
    }

    public function getContextConfig(): ContextInterface
    {
        return $this->context;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}