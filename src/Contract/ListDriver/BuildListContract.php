<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\ListDriver;

use HeimrichHannot\FlareBundle\List\ListCompoundBuilder;

/**
 * Implemented by list types that take part in their list's build lifecycle —
 * adding filters or config overrides before the ListCompound is built.
 */
interface BuildListContract
{
    public function buildList(ListCompoundBuilder $builder): void;
}
