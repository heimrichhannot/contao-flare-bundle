<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

interface AggregationLoaderInterface
{
    public function fetchCount(): int;
}