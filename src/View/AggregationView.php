<?php

namespace HeimrichHannot\FlareBundle\View;

class AggregationView implements ViewInterface
{
    private int $count;

    public function __construct(
        private readonly \Closure $fetchCount,
    ) {}

    public function getCount(): int
    {
        return $this->count ??= ($this->fetchCount)();
    }
}