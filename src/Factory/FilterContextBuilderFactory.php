<?php

namespace HeimrichHannot\FlareBundle\Factory;

use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;

class FilterContextBuilderFactory
{
    public function __construct() {}

    public function create(): FilterContextBuilder
    {
        return new FilterContextBuilder();
    }
}