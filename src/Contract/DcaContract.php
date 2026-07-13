<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;

/**
 * Implemented by filter elements and list types that configure their Contao backend
 * appearance: palette, field definitions, option providers, and load/save transforms.
 *
 * Optional — elements without a backend presence (e.g., inline elements) implement nothing.
 */
interface DcaContract
{
    public function buildDca(DcaBuilder $dca, DcaContext $context): void;
}
