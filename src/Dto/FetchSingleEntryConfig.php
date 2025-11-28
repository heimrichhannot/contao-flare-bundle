<?php

namespace HeimrichHannot\FlareBundle\Dto;

use HeimrichHannot\FlareBundle\Filter\FilterContext;

class FetchSingleEntryConfig
{
    public function __construct(
        public int $id,
        public ?FilterContext $idFilterContext = null,
    ) {}
}