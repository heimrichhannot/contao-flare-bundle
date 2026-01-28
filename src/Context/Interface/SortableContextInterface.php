<?php

namespace HeimrichHannot\FlareBundle\Context\Interface;

use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;

interface SortableContextInterface
{
    public function getSortDescriptor(): ?SortDescriptor;
}