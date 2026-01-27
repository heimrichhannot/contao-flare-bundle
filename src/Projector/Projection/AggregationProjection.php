<?php

namespace HeimrichHannot\FlareBundle\Projector\Projection;

class AggregationProjection implements ProjectionInterface
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