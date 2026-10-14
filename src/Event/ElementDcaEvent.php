<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after an element's configureDca() ran, before the builder is applied to
 * the live DCA. Listeners may adjust the palette or field configuration — also across
 * types via the named events `flare.filter_element.{type}.dca` / `flare.list.{type}.dca`.
 */
class ElementDcaEvent extends Event
{
    public function __construct(
        public readonly DcaBuilder $dca,
        public readonly DcaContext $context,
    ) {}
}
