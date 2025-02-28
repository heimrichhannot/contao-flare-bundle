<?php

namespace HeimrichHannot\FlareBundle\Contao;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;

interface TypePaletteInterface
{
    public function getPalette(string $alias, DataContainer $dc): PaletteManipulator|string|null;
}