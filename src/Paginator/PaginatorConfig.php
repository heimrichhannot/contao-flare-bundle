<?php

namespace HeimrichHannot\FlareBundle\Paginator;

readonly class PaginatorConfig
{
    protected int $currentPage;
    protected int $itemsPerPage;

    public function __construct(
        ?int $currentPage = null,
        ?int $itemsPerPage = null,
    ) {
        $this->currentPage = $currentPage ?? 1;
        $this->itemsPerPage = $itemsPerPage ?? 0;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function __toString(): string
    {
        return \serialize([
            'itemsPerPage' => $this->itemsPerPage,
        ]);
    }
}