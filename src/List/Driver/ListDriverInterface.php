<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Driver;

/**
 * A FLARE list driver — registered via #[AsListDriver] or used inline on a ListSpec.
 */
interface ListDriverInterface
{
    /**
     * Returns the main data container table of a list, derived from its canonical config.
     * Drivers pinned to a single table may ignore the config and return that table.
     *
     * @param array<string, mixed> $config Canonical, resolved list config.
     */
    public function getDataContainerName(array $config): string;
}
