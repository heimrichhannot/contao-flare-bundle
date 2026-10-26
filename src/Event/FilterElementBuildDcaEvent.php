<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a filter element's `buildDca()` and the framework fields (control segment, `value`) ran, before the builder is applied to the live DCA.
 * Listeners may adjust the palette or field configuration — also per type via the named event
 * `flare.filter_element.{type}.dca` (the element type).
 */
class FilterElementBuildDcaEvent extends Event
{
    public function __construct(
        public readonly DcaBuilder $builder,
        public readonly DcaContext $context,
    ) {}
}
