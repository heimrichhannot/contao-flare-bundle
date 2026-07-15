<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per list type class when its transformer map is configured.
 * Listeners may register transformers for additional source classes — also per type
 * via the named event `flare.list.{type}.transformers`.
 */
class ListTransformerEvent extends Event
{
    public function __construct(
        public readonly TransformerResolver $transformers,
        public readonly ListDriverInterface $driver,
        public readonly ?string             $type,
    ) {}
}
