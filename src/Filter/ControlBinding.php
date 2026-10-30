<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;

/**
 * The filter control of a {@see Filter}, together with its registered type and resolved config.
 *
 * @api
 */
final readonly class ControlBinding
{
    /**
     * @param FilterControlInterface $component The control presenting the filter.
     * @param string $type Registered control type, or the class name of an inline control. Only used for named
     *   event dispatch (`flare.filter_control.{type}.*`).
     * @param ComponentConfig $config Canonical control config (control-defined schema).
     */
    public function __construct(
        public FilterControlInterface $component,
        public string                 $type,
        public ComponentConfig        $config,
    ) {}
}
