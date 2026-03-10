<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\ListType;

use Contao\DataContainer;

interface DataContainerContract
{
    public function getDataContainerName(array $row, DataContainer $dc): string;
}