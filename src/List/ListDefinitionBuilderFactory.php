<?php

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Manager\FilterDefinitionManager;

readonly class ListDefinitionBuilderFactory
{
    public function __construct(
        private FilterDefinitionManager $filterDefinitionManager,
    ) {}

    public function create(): ListDefinitionBuilder
    {
        return new ListDefinitionBuilder($this->filterDefinitionManager);
    }
}