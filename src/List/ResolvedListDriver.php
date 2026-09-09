<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;

final readonly class ResolvedListDriver
{
    public function __construct(
        public string $type,
        public ListDriverInterface $driver,
    ) {}
}
