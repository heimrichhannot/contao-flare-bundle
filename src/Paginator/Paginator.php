<?php

namespace HeimrichHannot\FlareBundle\Paginator;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class Paginator extends PaginatorConfig
{
    public const DEFAULT_WINDOW_PAGES = 5;
    public const DEFAULT_FRAME_PAGES = 1;

    private string $routeName;
    private array $routeParams;

    public function __construct(
        private int                   $currentPage,
        private int                   $itemsPerPage,
        private int                   $totalItems,
        private int                   $lastPage,
        private ?int                  $previousPage,
        private ?int                  $nextPage,
        private int                   $firstItemNumber,
        private int                   $lastItemNumber,
        private bool                  $hasNextPage,
        private bool                  $hasPreviousPage,
        private RequestStack          $requestStack,
        private UrlGeneratorInterface $urlGenerator,
        private ?string               $queryPrefix = null,
        ?string                       $routeName = null,
        ?array                        $routeParams = null,
    ) {
        parent::__construct($itemsPerPage);

        $this->routeName = $routeName ?? $this->getCurrentRouteName();
        $this->routeParams = $routeParams ?? $this->getCurrentRouteParams();
    }

    public function isEmpty(): bool
    {
        return $this->totalItems === 0;
    }

    public function isLimited(): bool
    {
        return $this->itemsPerPage > 0;
    }

    public function getCurrentPageNumber(): int
    {
        return $this->currentPage;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getLastPageNumber(): int
    {
        return $this->lastPage;
    }

    public function getPreviousPageNumber(): ?int
    {
        return $this->previousPage;
    }

    public function getNextPageNumber(): ?int
    {
        return $this->nextPage;
    }

    public function getFirstItemNumber(): int
    {
        return $this->firstItemNumber;
    }

    public function getLastItemNumber(): int
    {
        return $this->lastItemNumber;
    }

    public function hasNextPage(): bool
    {
        return $this->hasNextPage;
    }

    public function hasPreviousPage(): bool
    {
        return $this->hasPreviousPage;
    }

    public function getCurrent(): PageItem
    {
        return $this->getPageItem($this->currentPage);
    }

    public function getPrevious(): ?PageItem
    {
        return $this->hasPreviousPage ? $this->getPageItem($this->previousPage) : null;
    }

    public function getNext(): ?PageItem
    {
        return $this->hasNextPage ? $this->getPageItem($this->nextPage) : null;
    }

    public function getFirst(): PageItem
    {
        return $this->getPageItem(1);
    }

    public function getLast(): PageItem
    {
        return $this->getPageItem($this->lastPage);
    }

    public function getPageItem($page): PageItem
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
            isEllipsis: false
        );
    }
    
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

    public function getWindow(int $maxPages = self::DEFAULT_WINDOW_PAGES): iterable
    {
        $range = $this->getPageNumberWindow($maxPages);

        foreach ($range as $page)
        {
            yield $this->getPageItem($page);
        }
    }

    public function getFrameLeft(
        int $maxFramePages = self::DEFAULT_FRAME_PAGES,
        int $maxWindowPages = self::DEFAULT_WINDOW_PAGES
    ): iterable {
        $window = $this->getPageNumberWindow($maxWindowPages);
        $range = \range(1, \min($window[0] - 1, $maxFramePages));

        foreach ($range as $page)
        {
            yield $this->getPageItem($page);
        }
    }

    public function getFrameRight(
        int $maxFramePages = self::DEFAULT_FRAME_PAGES,
        int $maxWindowPages = self::DEFAULT_WINDOW_PAGES
    ): iterable {
        $window = $this->getPageNumberWindow($maxWindowPages);
        $range = \range(\max($window[\count($window) - 1] + 1, $this->lastPage - $maxFramePages + 1), $this->lastPage);

        foreach ($range as $page)
        {
            yield $this->getPageItem($page);
        }
    }

    public function getBoard(
        int $maxWindowPages = self::DEFAULT_WINDOW_PAGES,
        int $maxFramePages = self::DEFAULT_FRAME_PAGES,
        bool $ellipsis = true
    ): iterable {
        if ($this->isEmpty() || !$this->isLimited()) {
            return;
        }

        $window = $this->getPageNumberWindow($maxWindowPages);
        $windowStart = $window[0];
        $windowEnd = $window[\count($window) - 1];

        // Left frame
        $frameLeftEnd = \min($windowStart - 1, $maxFramePages);
        for ($page = 1; $page <= $frameLeftEnd; $page++) {
            yield $this->getPageItem($page);
        }

        // Left ellipsis
        if ($ellipsis) {
            $leftGap = $windowStart - $frameLeftEnd - 1;
            if ($leftGap === 1) {
                yield $this->getPageItem($frameLeftEnd + 1);
            } elseif ($leftGap > 1) {
                yield PageItem::newEllipsis();
            }
        }

        // Window pages
        foreach ($window as $page) {
            yield $this->getPageItem($page);
        }

        // Right ellipsis
        if ($ellipsis) {
            $rightGap = ($this->lastPage - $maxFramePages) - $windowEnd;
            if ($rightGap === 1) {
                yield $this->getPageItem($windowEnd + 1);
            } elseif ($rightGap > 1) {
                yield PageItem::newEllipsis();
            }
        }

        // Right frame
        $frameRightStart = \max($windowEnd + 1, $this->lastPage - $maxFramePages + 1);
        for ($page = $frameRightStart; $page <= $this->lastPage; $page++) {
            yield $this->getPageItem($page);
        }
    }

    /**
     * Returns an array of page numbers for pagination navigation
     * @return array<int>
     */
    public function getPageNumberWindow(int $maxPages): array
    {
        $start = \max(1, $this->currentPage - \floor($maxPages / 2));
        $end = \min($this->lastPage, $start + $maxPages - 1);

        // Adjust start if we're near the end
        $start = \max(1, \min($start, $this->lastPage - $maxPages + 1));

        return \range((int) $start, (int) $end);
    }

    /**
     * Returns the offset for database queries
     */
    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }

    public function getPageUrl(int $page): string
    {
        return $this->urlGenerator->generate(
            $this->routeName,
            \array_merge($this->routeParams, [$this->getPageParameter() => $page])
        );
    }

    private function getCurrentRouteName(): string
    {
        $request = $this->getCurrentRequest();
        return $request->attributes->get('_route');
    }

    private function getCurrentRouteParams(): array
    {
        $request = $this->getCurrentRequest();
        $params = $request->attributes->get('_route_params', []);

        $pageParam = $this->getPageParameter();

        // Merge query parameters, excluding the page parameter
        $queryParams = \array_filter(
            $request->query->all(),
            static fn(string $key) => $key !== $pageParam,
            \ARRAY_FILTER_USE_KEY
        );

        return \array_merge($params, $queryParams);
    }

    private function getCurrentRequest(): Request
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request->attributes->get('_route')) {
            throw new \RuntimeException('No route found in current request');
        }
        return $request;
    }

    public function getPageParameter(): string
    {
        return self::pageParam($this->queryPrefix);
    }

    public static function pageParam(string $prefix = null): string
    {
        $prefix = \preg_replace(['/[^a-z0-9_]/i', '/_?page$/i', '/_{2,}/'], ['_', '', '_'], $prefix);
        return $prefix ? \rtrim($prefix, '_') . '_page' : 'page';
    }
}