<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a list driver's `buildDca()` ran, before the builder is applied to the live DCA.
 * Listeners may adjust the palette or field configuration — also per type via the named event
 * `flare.list.{type}.dca`.
 */
class ListDcaEvent extends Event
{
    public function __construct(
        public readonly DcaBuilder $builder,
        public readonly DcaContext $context,
    ) {}
}
