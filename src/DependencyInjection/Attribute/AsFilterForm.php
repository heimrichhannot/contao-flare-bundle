<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterFormsPass;
use HeimrichHannot\FlareBundle\DependencyInjection\Factory\TypeNameFactory;

/**
 * Registers a filter form and declares which value class it produces.
 *
 * @see RegisterFilterFormsPass The compile-time consumer.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsFilterForm
{
    public const TAG = 'flare.filter_form';

    public ?string $name;
    /** @var array<string, mixed> */
    public array $attributes;

    /**
     * @param string|null $name Stable identifier persisted in `tl_flare_filter.formVariant`.
     *   Defaults to {@see TypeNameFactory::createFilterFormType()} over the service class. Prefix
     *   third-party names; the empty string is reserved for "no form" (intrinsic).
     * @param list<class-string> $requires Capability interfaces the element must implement for this
     *   form to be offered for it (plain `instanceof`).
     */
    public function __construct(
        ?string        $name = null,
        public array   $requires = [],
        mixed          ...$attributes
    ) {
        $this->name = $name;

        $attributes['name'] = $this->name;
        $attributes['requires'] = $this->requires;

        $this->attributes = $attributes;
    }
}
