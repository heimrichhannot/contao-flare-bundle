<?php

namespace HeimrichHannot\FlareBundle\Dto;

class InvokeFiltersResult
{
    public function __construct(
        public array $conditions = [],
        public array $parameters = [],
        public array $types = [],
        public array $tablesUsed = [],
    ) {}
}