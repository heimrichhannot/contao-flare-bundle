<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Filter\Type\FilterTypeInterface;

readonly class FilterCall
{
    public function __construct(
        public FilterTypeInterface $type,
        public string              $typeClass,
        public string              $targetAlias,
        public array               $options,
    ) {}
}