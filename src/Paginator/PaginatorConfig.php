<?php

namespace HeimrichHannot\FlareBundle\Paginator;

readonly class PaginatorConfig
{
    private int $itemsPerPage;

    public function __construct(
        ?int $itemsPerPage = null,
    ) {
        $this->itemsPerPage = $itemsPerPage ?? 0;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }
}