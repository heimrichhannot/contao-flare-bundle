<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

/**
 * The filter element of a {@see FilterCompound}, together with its registered type, resolved config and value class.
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
     * @param class-string<ValueInterface>|null $valueClass The value class the element consumes, from its
     *   registration (or its {@see AsFilterElement} attribute for inline instances); null for an element
     *   without a runtime value (intrinsic).
     */
    public function __construct(
        public FilterElementInterface $component,
        public string                 $type,
        public ComponentConfig        $config,
        public ?string                $valueClass,
    ) {}
}
