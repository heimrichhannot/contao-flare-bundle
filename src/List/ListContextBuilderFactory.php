<?php

namespace HeimrichHannot\FlareBundle\List;

readonly class ListContextBuilderFactory
{
    public function create(): ListContextBuilder
    {
        return new ListContextBuilder();
    }
}