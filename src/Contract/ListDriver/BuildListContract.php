<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\ListType;

use HeimrichHannot\FlareBundle\List\ListSpecBuilder;

/**
 * Implemented by list types that take part in their list's build lifecycle —
 * adding filters or config overrides before the ListSpec is built.
 */
interface BuildListContract
{
    public function buildList(ListSpecBuilder $builder): void;
}
