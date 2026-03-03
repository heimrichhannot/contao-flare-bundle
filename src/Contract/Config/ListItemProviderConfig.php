<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class ListItemProviderConfig
{
    public function __construct(
        private ListSpecification $listSpecification,
    ) {}

    public function getListSpecification(): ListSpecification
    {
        return $this->listSpecification;
    }
}