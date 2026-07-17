<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\ListDriver;

use Contao\DataContainer;

/** @api Implement on a ListDriver to resolve a data container for list config storage. */
interface DataContainerContract
{
    /** @internal Used internally to resolve the data container table for a given row and data container. */
    public function resolveDataContainerTable(array $row, DataContainer $dc): string;
}
