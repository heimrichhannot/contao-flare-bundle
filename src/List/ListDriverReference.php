<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\List\Type\ListDriverInterface;

final readonly class ListDriverReference
{
    public function __construct(
        public string              $type,
        public ListDriverInterface $driver,
    ) {}
}
