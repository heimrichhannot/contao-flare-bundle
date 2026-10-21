<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterControlsPass;
use HeimrichHannot\FlareBundle\DependencyInjection\Factory\TypeNameFactory;

/**
 * Registers a filter control. The value class is not declared here: it is the narrowed return
 * type of the control's `decode()`.
 *
 * @see RegisterFilterControlsPass The compile-time consumer.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsFilterControl
{
    public const TAG = 'flare.filter_control';

    public ?string $name;
    /** @var array<string, mixed> */
    public array $attributes;

    /**
     * @param string|null $name Stable identifier persisted in `tl_flare_filter.control`. Defaults to
     *   {@see TypeNameFactory::createFilterControlType()} over the service class. Prefix third-party
     *   names; the empty string (intrinsic) and "default" are reserved.
     */
    public function __construct(
        ?string $name = null,
        mixed   ...$attributes
    ) {
        $this->name = $name;

        $attributes['name'] = $this->name;

        $this->attributes = $attributes;
    }
}
