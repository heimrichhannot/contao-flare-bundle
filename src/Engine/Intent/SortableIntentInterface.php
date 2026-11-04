<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Intent;

use HeimrichHannot\FlareBundle\Sort\SortOrderSequence;

interface SortableIntentInterface
{
    public function getSortOrderSequence(): ?SortOrderSequence;
}
