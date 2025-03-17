<?php

namespace HeimrichHannot\FlareBundle\Paginator;

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
    ) {}

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
        );
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function isCurrent(): bool
    {
        return $this->isCurrent;
    }

    public function isFirst(): bool
    {
        return $this->isFirst;
    }

    public function isLast(): bool
    {
        return $this->isLast;
    }

    public function isPrevious(): bool
    {
        return $this->isPrevious;
    }

    public function isNext(): bool
    {
        return $this->isNext;
    }

    public function hasPrevious(): bool
    {
        return $this->hasPrevious;
    }

    public function hasNext(): bool
    {
        return $this->hasNext;
    }

    public function isEllipsis(): bool
    {
        return $this->isEllipsis;
    }
}