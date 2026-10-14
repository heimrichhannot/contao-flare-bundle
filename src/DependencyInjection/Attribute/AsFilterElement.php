<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsFilterElement
{
    public const TAG = 'huh.flare.filter_element';

    public array $attributes;

    /**
     * @param ?string $type
     * @param bool|null $isTargeted
     * @param mixed ...$attributes
     */
    public function __construct(
        ?string $type = null,
        ?bool   $isTargeted = null,
        mixed   ...$attributes
    ) {
        $attributes['type'] = $type ?? $attributes['alias'] ?? null;
        $attributes['isTargeted'] = $isTargeted;

        $this->attributes = $attributes;
    }
}
