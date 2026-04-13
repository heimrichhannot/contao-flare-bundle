<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\View;

use HeimrichHannot\FlareBundle\Engine\Loader\AggregationLoaderInterface;

readonly class AggregationView implements ViewInterface
{
    private int $count;

    public function __construct(
        private AggregationLoaderInterface $loader,
    ) {}

    public function getCount(): int
    {
        return $this->count ??= $this->loader->fetchCount();
    }
}