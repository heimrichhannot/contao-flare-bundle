<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsFilterElement
{
    public const TAG = 'flare.filter_element';

    public ?string $type;
    public array $attributes;

    /**
     * @param class-string|null $value The value class this element consumes in buildFilter().
     *   Null means the element has no runtime value at all, i.e. it is intrinsic-only.
     */
    public function __construct(
        ?string        $type = null,
        public ?bool   $isTargeted = null,
        public ?string $value = null,
        mixed          ...$attributes
    ) {
        $this->type = $type ?? $attributes['alias'] ?? null;

        $attributes['type'] = $this->type;
        $attributes['isTargeted'] = $isTargeted;
        $attributes['value'] = $value;

        $this->attributes = $attributes;
    }
}
