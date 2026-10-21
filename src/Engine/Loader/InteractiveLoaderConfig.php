<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;

readonly class InteractiveLoaderConfig
{
    /**
     * @param array<string, ?ValueInterface> $filterValues Filter values keyed by filter alias.
     */
    public function __construct(
        public ListSpec  $list,
        public InteractiveContext $context,
        public array              $filterValues,
    ) {}
}