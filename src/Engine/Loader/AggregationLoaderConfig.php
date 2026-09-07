<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Filter\FilterData;
use HeimrichHannot\FlareBundle\List\ListSpec;

readonly class AggregationLoaderConfig
{
    /**
     * @param array<string|int, FilterData> $filterValues
     */
    public function __construct(
        public ListSpec           $list,
        public AggregationContext $context,
        public array              $filterValues,
    ) {}
}
