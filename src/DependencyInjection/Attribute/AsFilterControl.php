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

    public ?string $type;
    /** @var array<string, mixed> */
    public array $attributes;

    /**
     * @param string|null $type Stable identifier persisted in `tl_flare_filter.control`. Defaults to
     *   {@see TypeNameFactory::createFilterControlType()} over the service class. Prefix third-party
     *   types; the empty string (intrinsic) and "default" are reserved.
     */
    public function __construct(
        ?string $type = null,
        mixed   ...$attributes
    ) {
        $this->type = $type;

        $attributes['type'] = $this->type;

        $this->attributes = $attributes;
    }
}
