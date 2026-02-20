<?php

namespace HeimrichHannot\FlareBundle\Paginator;

readonly class PaginatorConfig implements \Countable, \Serializable, \Stringable
{
    protected int $itemsPerPage;
    protected int $currentPage;
    protected int $totalItems;
    private int $_firstItemNumber;
    private int $_lastItemNumber;
    private int $_lastPageNumber;

    /**
     * @param int|null $itemsPerPage The number of items per page, or 0 for unlimited. Set null to default to unlimited.
     * @param int|null $currentPage The current page number, always greater than 0. Set null to default to 1.
     * @param int|null $totalItems The total number of items, or -1 if unknown. Set null to default to unknown.
     */
    public function __construct(
        ?int $itemsPerPage = null,
        ?int $currentPage = null,
        ?int $totalItems = null,
    ) {
        $this->itemsPerPage = \max(0, $itemsPerPage ?? 0);
        $this->currentPage = \max(1, $currentPage ?? 1);
        $this->totalItems = \max(-1, $totalItems ?? -1);
    }

    /**
     * Get the current page number.
     * @api
     * @return int The current page number, always greater than 0.
     */
    public function getCurrentPageNumber(): int
    {
        return $this->currentPage;
    }

    /**
     * Get the number of items per page.
     * @api
     * @return int The number of items per page or 0 if unlimited.
     */
    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    /**
     * Get the number of items across all pages.
     *
     * @return ?int The total number of items, or null if unknown.
     * @api
     */
    public function getTotalItems(): ?int
    {
        return $this->totalItems >= 0 ? $this->totalItems : null;
    }

    /**
     * Get the number of the first item on the current page.
     * @api
     * @return int The first item number, always greater than 0.
     */
    public function getFirstItemNumber(): int
    {
        return $this->_firstItemNumber ??= ($this->currentPage - 1) * $this->itemsPerPage + 1;
    }

    /**
     * Get the number of the last item on the current page.
     * @api
     * @return int The last item number, always greater than 0.
     */
    public function getLastItemNumber(): int
    {
        if (isset($this->_lastItemNumber)) {
            return $this->_lastItemNumber;
        }

        $lastPossibleItem = $this->currentPage * $this->itemsPerPage;

        if ($this->totalItems > 0) {
            $lastPossibleItem = \min($lastPossibleItem, $this->totalItems);
        }

        return $this->_lastItemNumber ??= (int) \max($lastPossibleItem, $this->getFirstItemNumber());
    }

    /**
     * Get the number of the first page.
     * @api
     * @return int The first page number, always 1.
     */
    public function getFirstPageNumber(): int
    {
        return 1;
    }

    /**
     * Returns the number of the last page.
     * @api
     * @return int|null The last page number, or null if the total number of items is unknown.
     */
    public function getLastPageNumber(): ?int
    {
        if (isset($this->_lastPageNumber)) {
            return $this->_lastPageNumber;
        }

        if ($this->itemsPerPage < 1 || $this->totalItems < 0) {
            return null;
        }

        return $this->_lastPageNumber = (int) \ceil($this->totalItems / $this->itemsPerPage);
    }

    /**
     * Get the number of the previous page.
     * @api
     * @return int|null The previous page number, or `null` if there is no previous page.
     */
    public function getPreviousPageNumber(): ?int
    {
        return $this->currentPage > 1 ? $this->currentPage - 1 : null;
    }

    /**
     * Get the number of the next page.
     * @api
     * @return int|null The next page number, or `null` if there is no next page.
     */
    public function getNextPageNumber(): ?int
    {
        return $this->currentPage < $this->getLastPageNumber() ? $this->currentPage + 1 : null;
    }

    /**
     * Returns true if there is a previous page.
     * @api
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Returns true if there is a next page.
     * @api
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getLastPageNumber();
    }

    public function getCurrentPageItemCount(): int
    {
        return $this->getLastItemNumber() - $this->getFirstItemNumber();
    }

    /**
     * Returns true if the paginator is configured to limit the number of items per page.
     * @api
     */
    public function isLimited(): bool
    {
        return $this->itemsPerPage > 0;
    }

    /**
     * Returns the offset for database queries.
     */
    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }

    public function with(
        int $itemsPerPage = null,
        int $currentPage = null,
        int $totalItems = null,
    ): static {
        return new static(
            itemsPerPage: $itemsPerPage ?? $this->itemsPerPage,
            currentPage: $currentPage ?? $this->currentPage,
            totalItems: $totalItems ?? $this->totalItems,
        );
    }

    public function count(): int
    {
        return $this->getLastPageNumber();
    }

    public function serialize(): string
    {
        return \serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(\unserialize($data, ['allowed_classes' => false]));
    }

    public function __serialize(): array
    {
        return [
            'itemsPerPage' => $this->itemsPerPage,
            'currentPage' => $this->currentPage,
            'totalItems' => $this->totalItems,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->itemsPerPage = $data['itemsPerPage'];
        $this->currentPage = $data['currentPage'];
        $this->totalItems = $data['totalItems'];
    }

    public function __toString(): string
    {
        return \sprintf(
            '[ page %d of %s | %d items of %s total | indices %d to %d ]',
            $this->currentPage,
            (string) ($this->getLastPageNumber() ?? 'many'),
            $this->getCurrentPageItemCount(),
            (string) ($this->getTotalItems() ?? 'unknown'),
            $this->getFirstItemNumber(),
            $this->getLastItemNumber(),
        );
    }
}