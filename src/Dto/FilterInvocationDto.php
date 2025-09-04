<?php

namespace HeimrichHannot\FlareBundle\Dto;

class FilterInvocationDto
{
    public function __construct(
        public array $conditions = [],
        public array $parameters = [],
        public array $types = [],
        public array $tablesUsed = [],
    ) {}
}