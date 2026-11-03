<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per filter element class and type when its transformer map is configured, after the element's
 * `configureTransformers()` ran. Listeners may set transformers for additional source classes or replace
 * the element's own — also per type via the named event `flare.filter_element.{type}.transformers`.
 */
class FilterElementConfigureTransformersEvent extends Event
{
    public function __construct(
        public readonly TransformerMap         $transformers,
        public readonly FilterElementInterface $element,
        public readonly string                 $type,
    ) {}
}
