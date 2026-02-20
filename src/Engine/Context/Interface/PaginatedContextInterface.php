<?php

namespace HeimrichHannot\FlareBundle\Engine\Context\Interface;

use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;

interface PaginatedContextInterface
{
    public function getPaginatorConfig(): PaginatorConfig;

    public function getPaginatorQueryParameter(): ?string;
}