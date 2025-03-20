<?php

namespace HeimrichHannot\FlareBundle\Filter\Builder;

class FilterContextBuilderFactory
{
    public function __construct() {}

    public function create(): FilterContextBuilder
    {
        return new FilterContextBuilder();
    }
}