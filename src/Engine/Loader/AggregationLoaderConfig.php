<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Intent\AggregationIntent;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;

readonly class AggregationLoaderConfig
{
    /**
     * @param array<string, ?ValueInterface> $filterValues Filter values keyed by filter alias.
     */
    public function __construct(
        public ListCompound      $list,
        public AggregationIntent $intent,
        public array             $filterValues,
    ) {}
}
