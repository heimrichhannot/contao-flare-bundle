<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;

interface PaletteContract
{
    public function getPalette(PaletteConfig $config): ?string;
}