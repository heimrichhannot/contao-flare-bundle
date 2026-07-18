<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\ListDriver;

use Contao\DataContainer;

/** @api Implement on a ListDriver to resolve a data container for list config storage. */
interface OnSubmitDcContract
{
    /** @internal Used internally to resolve the data container table for a given row and data container. */
    public function resolveDcOnSubmit(array $row, DataContainer $dc): string;
}
