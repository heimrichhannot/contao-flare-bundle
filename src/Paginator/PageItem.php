<?php

namespace HeimrichHannot\FlareBundle\Paginator;

/**
 * Represents a page item in a paginator.
 * A page item can represent a link to a specific page or a placeholder between page items (e.g. an ellipsis).
 */
readonly class PageItem
{
    public function __construct(
        private int    $number,
        private string $url,
        private bool   $isCurrent,
        private bool   $isFirst,
        private bool   $isLast,
        private bool   $isPrevious,
        private bool   $isNext,
        private bool   $hasPrevious,
        private bool   $hasNext,
        private bool   $isEllipsis = false,
        private bool   $isFiller = false,
    ) {}

    /**
     * Create a new empty page item representing an ellipsis.
     */
    public static function newEllipsis(): PageItem
    {
        return new self(
            number: 0,
            url: '',
            isCurrent: false,
            isFirst: false,
            isLast: false,
            isPrevious: false,
            isNext: false,
            hasPrevious: true,
            hasNext: true,
            isEllipsis: true,
            isFiller: true,
        );
    }

    /**
     * Returns the page number.
     * @api
     */
    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * Returns the URL for the page.
     * @api
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns whether the page is the current page.
     * @api
     */
    public function isCurrent(): bool
    {
        return $this->isCurrent;
    }

    /**
     * Returns whether the page is the first page.
     * @api
     */
    public function isFirst(): bool
    {
        return $this->isFirst;
    }

    /**
     * Returns whether the page is the last page.
     * @api
     */
    public function isLast(): bool
    {
        return $this->isLast;
    }

    /**
     * Returns whether the page is the previous page to the current page.
     * @api
     */
    public function isPrevious(): bool
    {
        return $this->isPrevious;
    }

    /**
     * Returns whether the page is the next page to the current page.
     * @api
     */
    public function isNext(): bool
    {
        return $this->isNext;
    }

    /**
     * Returns whether the page has a previous page.
     * @api
     */
    public function hasPrevious(): bool
    {
        return $this->hasPrevious;
    }

    /**
     * Returns whether the page has a next page.
     * @api
     */
    public function hasNext(): bool
    {
        return $this->hasNext;
    }

    /**
     * Returns whether the page is an ellipsis.
     * @api
     */
    public function isEllipsis(): bool
    {
        return $this->isEllipsis;
    }

    /**
     * Returns whether the page is a filler, i.e. an ellipsis or a page in place thereof.
     * @api
     */
    public function isFiller(): bool
    {
        return $this->isFiller;
    }
}