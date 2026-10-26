<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per list driver class and type when its transformer map is configured, after the driver's
 * `configureTransformers()` ran. Listeners may set transformers for additional source classes or replace the
 * driver's own — also per type via the named event `flare.list_driver.{type}.transformers`.
 */
class ListDriverConfigureTransformersEvent extends Event
{
    public function __construct(
        public readonly TransformerMap      $transformers,
        public readonly ListDriverInterface $driver,
        public readonly string              $type,
    ) {}
}
