<?php

namespace HeimrichHannot\FlareBundle\Context\Interface;

use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;

interface PaginatedContextInterface
{
    public function getPaginatorConfig(): PaginatorConfig;
}