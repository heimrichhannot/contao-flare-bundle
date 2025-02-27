<?php

namespace HeimrichHannot\FlareBundle\Contao;

use Contao\DataContainer;

interface TypeSubpaletteInterface
{
    public function getSubpalette(string $alias, DataContainer $dc): string;
}