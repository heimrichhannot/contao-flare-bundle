<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Intent\InteractiveIntent;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;

readonly class InteractiveLoaderConfig
{
    /**
     * @param array<string, ?ValueInterface> $filterValues Filter values keyed by filter alias.
     */
    public function __construct(
        public ListCompound      $list,
        public InteractiveIntent $intent,
        public array             $filterValues,
    ) {}
}
