<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Driver;

/**
 * A Flare list driver, registered via #[AsListDriver] or used inline on a ListSpec.
 */
interface ListDriverInterface
{
    /**
     * Returns the main data container table of a list, derived from its canonical config and,
     * if registered via #[AsListDriver], the driver's attributes.
     * Drivers pinned to a single table may ignore the config and return that table.
     *
     * @param string $type The registered type alias of the list driver.
     * @param array<string, mixed> $config Canonical, resolved list config.
     * @param array<string, mixed> $attributes The registered attributes of the list driver.
     */
    public function resolveDcTable(string $type, array $config, array $attributes): string;
}
