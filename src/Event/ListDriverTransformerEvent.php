<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per list driver class when its transformer map is configured.
 * Listeners may register transformers for additional source classes — for registered
 * drivers also per type via the named event `flare.list_driver.{type}.transformers`.
 */
class ListDriverTransformerEvent extends Event
{
    public function __construct(
        public readonly TransformerMap $transformers,
        public readonly ListDriverInterface $driver,
        public readonly string              $type,
    ) {}
}
