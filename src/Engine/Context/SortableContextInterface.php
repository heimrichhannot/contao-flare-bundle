<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context;

use HeimrichHannot\FlareBundle\Sort\SortOrderSequence;

interface SortableContextInterface
{
    public function getSortOrderSequence(): ?SortOrderSequence;
}
