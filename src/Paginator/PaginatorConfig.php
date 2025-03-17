<?php

namespace HeimrichHannot\FlareBundle\Paginator;

readonly class PaginatorConfig
{
    public function __construct(
        private int $itemsPerPage = 0,
    ) {}

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }
}