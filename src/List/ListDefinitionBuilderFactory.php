<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Filter\Collector\FilterCollectors;

readonly class ListDefinitionBuilderFactory
{
    public function __construct(
        private FilterCollectors $collectors,
    ) {}

    public function create(): ListDefinitionBuilder
    {
        return new ListDefinitionBuilder($this->collectors);
    }
}