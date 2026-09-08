<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

use HeimrichHannot\FlareBundle\DependencyInjection\Compiler\RegisterFilterFormsPass;
use HeimrichHannot\FlareBundle\DependencyInjection\Factory\TypeNameFactory;

/**
 * Registers a filter form and declares which value class it produces.
 *
 * Repeatable: one form class may serve every element sharing a value class, and may serve several
 * value classes, without ever naming an element.
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
     * @param class-string|null $value The value class this form produces; the registry key.
     * @param list<class-string> $requires Capability interfaces the element must implement for this
     *   form to be offered for it (plain `instanceof`).
     * @param bool $default Whether this is the fallback form for $value. At most one default per
     *   value class.
     */
    public function __construct(
        ?string        $name = null,
        public ?string $value = null,
        public array   $requires = [],
        public bool    $default = false,
        mixed          ...$attributes
    ) {
        $this->name = $name;

        $attributes['name'] = $this->name;
        $attributes['value'] = $this->value;
        $attributes['requires'] = $this->requires;
        $attributes['default'] = $this->default;

        $this->attributes = $attributes;
    }
}
