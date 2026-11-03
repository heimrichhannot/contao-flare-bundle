<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per filter control class and type when its transformer map is configured, after the control's
 * `configureTransformers()` ran. Listeners may set transformers for additional source classes or replace
 * the control's own — also per type via the named event `flare.filter_control.{type}.transformers`.
 */
class FilterControlConfigureTransformersEvent extends Event
{
    public function __construct(
        public readonly TransformerMap         $transformers,
        public readonly FilterControlInterface $control,
        public readonly string                 $type,
    ) {}
}
