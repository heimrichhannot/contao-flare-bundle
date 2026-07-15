<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;

final readonly class ListDriverReference
{
    private function __construct(
        public string              $type,
        public ListDriverInterface $driver,
        public bool                $inline,
    ) {}

    /**
     * References a driver registered in the registry under the given type alias.
     */
    public static function registered(string $type, ListDriverInterface $driver): self
    {
        return new self(type: $type, driver: $driver, inline: false);
    }

    /**
     * References an inline driver instance; its class name stands in for the type alias.
     * Inline drivers take part in no `flare.list.{type}.*` named dispatch.
     */
    public static function inline(ListDriverInterface $driver): self
    {
        return new self(type: \get_class($driver), driver: $driver, inline: true);
    }
}
