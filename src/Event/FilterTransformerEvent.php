<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per filter element class when its transformer map is configured.
 * Listeners may register transformers for additional source classes — also per type
 * via the named event `flare.filter_element.{type}.transformers`.
 */
class FilterTransformerEvent extends Event
{
    public function __construct(
        public readonly TransformerResolver    $transformers,
        public readonly FilterElementInterface $element,
        public readonly string                 $type,
    ) {}
}
