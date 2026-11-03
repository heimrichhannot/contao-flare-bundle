<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;

/**
 * The filter element of a {@see Filter}, together with its registered type and resolved config.
 *
 * @api
 */
final readonly class ElementBinding
{
    /**
     * @param FilterElementInterface $component Filter element service (registered or inline).
     * @param string $type Registered element type alias, or the class name of an inline element. Only used for
     *   named event dispatch (`flare.filter_element.{type}.*`) and targeting lookups.
     * @param ComponentConfig $config Canonical element config (element-defined schema).
     */
    public function __construct(
        public FilterElementInterface $component,
        public string                 $type,
        public ComponentConfig        $config,
    ) {}
}
