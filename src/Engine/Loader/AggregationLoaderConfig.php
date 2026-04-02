<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Context\AggregationContext;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class AggregationLoaderConfig
{
    public function __construct(
        public ListSpecification  $list,
        public AggregationContext $context,
        public array              $filterValues,
    ) {}
}