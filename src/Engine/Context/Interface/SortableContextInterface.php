<?php

namespace HeimrichHannot\FlareBundle\Engine\Context\Interface;

use HeimrichHannot\FlareBundle\Sort\SortOrderSequence;

interface SortableContextInterface
{
    public function getSortOrderSequence(): ?SortOrderSequence;
}