<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Filter\FilterComponentInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per filter element or control class and type when its transformer map is configured.
 * Listeners may register transformers for additional source classes — also per type via the named
 * events `flare.filter_element.{type}.transformers` and `flare.filter_control.{type}.transformers`.
 */
class FilterTransformerEvent extends Event
{
    public function __construct(
        public readonly TransformerMap      $transformers,
        public readonly FilterComponentInterface $owner,
        public readonly string                   $type,
    ) {}
}
