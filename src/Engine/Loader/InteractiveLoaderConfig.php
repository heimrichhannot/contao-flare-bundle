<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class InteractiveLoaderConfig
{
    public function __construct(
        public ListSpecification  $list,
        public InteractiveContext $context,
        public array              $filterValues,
    ) {}
}