<?php

namespace HeimrichHannot\FlareBundle\Paginator;

readonly class Paginator extends PaginatorConfig
{
    public const DEFAULT_WINDOW_PADDING = 2;
    public const DEFAULT_FRAME_PAGES = 1;

    /**
     * @param \Closure(int): string $urlGenerator A closure that generates the URL for a given page number.
     */
    public function __construct(
        private int      $currentPage,
        private int      $itemsPerPage,
        private int      $totalItems,
        private int      $lastPage,
        private ?int     $previousPage,
        private ?int     $nextPage,
        private int      $firstItemNumber,
        private int      $lastItemNumber,
        private bool     $hasNextPage,
        private bool     $hasPreviousPage,
        private \Closure $urlGenerator,
        private ?string  $queryPrefix = null,
    ) {
        parent::__construct($itemsPerPage);
    }

    /**
     * Returns true if the paginator has no items.
     * @api
     */
    public function isEmpty(): bool
    {
        return $this->totalItems === 0;
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
     * Returns the page number of the current page.
     * @api
     */
    public function getCurrentPageNumber(): int
    {
        return $this->currentPage;
    }

    /**
     * Returns the number of items across all pages.
     * @api
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * Returns the number of the last page.
     * @api
     */
    public function getLastPageNumber(): int
    {
        return $this->lastPage;
    }

    /**
     * Returns the page number of the previous page.
     * @api
     */
    public function getPreviousPageNumber(): ?int
    {
        return $this->previousPage;
    }

    /**
     * Returns the page number of the next page.
     * @api
     */
    public function getNextPageNumber(): ?int
    {
        return $this->nextPage;
    }

    /**
     * Returns the number of the first item on the current page.
     * @api
     */
    public function getFirstItemNumber(): int
    {
        return $this->firstItemNumber;
    }

    /**
     * Returns the number of the last item on the current page.
     * @api
     */
    public function getLastItemNumber(): int
    {
        return $this->lastItemNumber;
    }

    /**
     * Returns true if there is a next page.
     * @api
     */
    public function hasNextPage(): bool
    {
        return $this->hasNextPage;
    }

    /**
     * Returns true if there is a previous page.
     * @api
     */
    public function hasPreviousPage(): bool
    {
        return $this->hasPreviousPage;
    }

    /**
     * Get the current page item.
     * @api
     */
    public function getCurrent(): PageItem
    {
        return $this->getPageItem($this->currentPage);
    }

    /**
     * Get the previous page item.
     * @api
     */
    public function getPrevious(): ?PageItem
    {
        return $this->hasPreviousPage ? $this->getPageItem($this->previousPage) : null;
    }

    /**
     * Get the next page item.
     * @api
     */
    public function getNext(): ?PageItem
    {
        return $this->hasNextPage ? $this->getPageItem($this->nextPage) : null;
    }

    /**
     * Get the first page item.
     * @api
     */
    public function getFirst(): PageItem
    {
        return $this->getPageItem(1);
    }

    /**
     * Get the last page item.
     * @api
     */
    public function getLast(): PageItem
    {
        return $this->getPageItem($this->lastPage);
    }

    /**
     * Returns a page item for the given page number.
     *
     * @param int       $page The page number to create the item for.
     * @param bool|null $isFiller Whether the item is a filler (i.e. ellipsis or in place there of) or not.
     */
    public function getPageItem(int $page, ?bool $isFiller = null): PageItem
    {
        return new PageItem(
            number: $page,
            url: $this->getPageUrl($page),
            isCurrent: $page === $this->currentPage,
            isFirst: $page === 1,
            isLast: $page === $this->lastPage,
            isPrevious: $page === $this->previousPage,
            isNext: $page === $this->nextPage,
            hasPrevious: $page > 1,
            hasNext: $page < $this->lastPage,
            isEllipsis: false,
            isFiller: $isFiller ?? false,
        );
    }

    /**
     * Returns an iterable of all available page items.
     *
     * @api
     */
    public function getPages(): iterable
    {
        if ($this->isEmpty()) {
            return;
        }

        for ($page = 1; $page <= $this->lastPage; $page++)
        {
            yield $this->getPageItem($page);
        }
    }

    /**
     * Returns an iterable of page items within the current page number window.
     *
     * @param int $padding The number of pages to show on each side of the current page.
     * @api
     */
    public function getWindow(int $padding = self::DEFAULT_WINDOW_PADDING): iterable
    {
        $range = $this->getPageNumberWindow($padding);

        foreach ($range as $page)
        {
            yield $this->getPageItem($page);
        }
    }

    /**
     * Returns an iterable of page items for the navigation bar with ellipses for gaps.
     *
     * @param int $windowPadding The number of pages to show on each side of the current page.
     * @param int $maxFramePages The maximum number of pages to show in the left and right frames.
     * @api
     */
    public function getNavigation(
        int $windowPadding = self::DEFAULT_WINDOW_PADDING,
        int $maxFramePages = self::DEFAULT_FRAME_PAGES,
    ): iterable {
        if ($this->isEmpty() || !$this->isLimited()) {
            return;
        }

        if ($this->lastPage <= 1) {
            yield $this->getPageItem(1);
            return;
        }

        // Define all page sets
        $windowPages = $this->getPageNumberWindow($windowPadding);
        $leftFramePages = $maxFramePages === 0
            ? []
            : \range(1, \min($maxFramePages, $this->lastPage));
        $rightFramePages = $maxFramePages === 0
            ? []
            : \range(\max(1, $this->lastPage - $maxFramePages + 1), $this->lastPage);

        // Create a set of all pages that should be visible
        $visiblePages = \array_unique(\array_merge($leftFramePages, $windowPages, $rightFramePages));
        \sort($visiblePages);

        // Yield pages with ellipses for gaps
        $prevPage = 0;
        foreach ($visiblePages as $page)
        {
            // Check if there's a gap
            if ($prevPage > 0 && $page - $prevPage > 1)
            {
                $gap = $page - $prevPage - 1;

                if ($gap === 1)
                    // Just one gap page, so show it
                {
                    yield $this->getPageItem($prevPage + 1, isFiller: true);
                }
                /** @mago-expect lint:no-else-clause This else clause is fine. */
                elseif ($gap > 1)
                    // Multiple gap pages, show ellipsis
                {
                    yield PageItem::newEllipsis();
                }
            }

            yield $this->getPageItem($page);
            $prevPage = $page;
        }
    }

    /**
     * Returns an array of page numbers: The current page padded by the given amount of surrounding pages.
     *
     * @example Use this method to create a window of page numbers for a pagination component.
     * ```php
     * $paginator->getCurrentPageNumber() === 5;
     * $paginator->getPageNumberWindow(2) === [3, 4, 5, 6, 7];
     * ```
     *
     * @return array<int>
     */
    public function getPageNumberWindow(int $padding): array
    {
        $maxPages = \max($padding, 0) + 1; // Ensure at least one page is shown
        $start = \max(1, $this->currentPage - \floor($maxPages / 2));
        $end = \min($this->lastPage, $start + $maxPages - 1);

        // Adjust start if we're near the end
        $start = \max(1, \min($start, $this->lastPage - $maxPages + 1));

        return \range((int) $start, (int) $end);
    }

    /**
     * Returns the offset for database queries.
     */
    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }

    /**
     * Returns the URL for the given page number.
     *
     * @param int $page
     * @return string
     * @api
     */
    public function getPageUrl(int $page): string
    {
        return ($this->urlGenerator)($page);
    }

    /**
     * Get the page parameter name for the current context.
     * @api
     */
    public function getPageParameter(): string
    {
        return self::pageParam($this->queryPrefix);
    }

    /**
     * Helper function to generate a page parameter name with a given prefix.
     *
     * If the prefix is null, the default page parameter name 'page' is returned.
     * Otherwise, the prefix is sanitized and appended with '_page'.
     *
     * @param string|null $prefix The prefix to use for the page parameter.
     * @return string The generated page parameter name.
     */
    public static function pageParam(string $prefix = null): string
    {
        $prefix = \preg_replace(['/[^a-z0-9_]/i', '/_?page$/i', '/_{2,}/'], ['_', '', '_'], $prefix);
        return $prefix ? \rtrim($prefix, '_') . '_page' : 'page';
    }
}