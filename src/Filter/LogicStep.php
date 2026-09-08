<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Filter\Logic\LogicInterface;

final readonly class LogicStep
{
    public function __construct(
        public LogicInterface $type,
        public string         $typeClass,
        public string         $targetAlias,
        public array          $options,
    ) {}
}
