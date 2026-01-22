<?php

namespace HeimrichHannot\FlareBundle\Dto;

use HeimrichHannot\FlareBundle\Filter\FilterDefinition;

class FetchSingleEntryConfig
{
    public function __construct(
        public int              $id,
        public FilterDefinition $idFilterDefinition,
    ) {}
}