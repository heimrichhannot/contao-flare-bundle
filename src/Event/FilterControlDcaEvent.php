<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after the selected filter control's `buildDca()` ran. The builder is scoped to the control
 * segment of the palette, so listeners adjust only the control's part. Also dispatched per control type
 * via the named event `flare.filter_control.{type}.dca`.
 *
 * `$context->type` is the element type; the control type is {@see self::$type}.
 */
class FilterControlDcaEvent extends Event
{
    public function __construct(
        public readonly DcaBuilderInterface $builder,
        public readonly DcaContext          $context,
        public readonly string              $type,
    ) {}
}
