<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract;

interface IsSupportedContract
{
    public function isSupported(): bool;
}