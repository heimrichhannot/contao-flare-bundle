<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use HeimrichHannot\FlareBundle\List\ListDefinition;

readonly class ListItemProviderConfig
{
    public function __construct(
        private ListDefinition $listDefinition,
    ) {}

    public function getListDefinition(): ListDefinition
    {
        return $this->listDefinition;
    }
}