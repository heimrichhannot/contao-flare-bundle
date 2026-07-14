<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

interface IntrinsicContract
{
    public function isOnlyIntrinsic(): bool;
}
