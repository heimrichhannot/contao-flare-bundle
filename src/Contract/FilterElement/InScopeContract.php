<?php

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\InScopeConfig;

interface InScopeContract
{
    public function isInScope(InScopeConfig $config): bool;
}