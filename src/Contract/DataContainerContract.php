<?php

namespace HeimrichHannot\FlareBundle\Contract;

use Contao\DataContainer;

interface DataContainerContract
{
    public function getDataContainerName(array $row, DataContainer $dc): string;
}