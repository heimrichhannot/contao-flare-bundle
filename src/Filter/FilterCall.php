<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Filter\Logic\FilterLogicInterface;

final readonly class FilterCall
{
    public function __construct(
        public FilterLogicInterface $type,
        public string               $typeClass,
        public string               $targetAlias,
        public array                $options,
    ) {}
}
