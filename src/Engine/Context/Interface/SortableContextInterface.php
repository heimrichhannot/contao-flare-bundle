<?php

namespace HeimrichHannot\FlareBundle\Engine\Context\Interface;

use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

interface SortableContextInterface
{
    public function getSortDescriptor(): ?SortDescriptor;
}