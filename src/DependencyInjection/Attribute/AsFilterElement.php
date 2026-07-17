<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsFilterElement
{
    public const TAG = 'huh.flare.filter_element';

    public ?string $type;
    public array $attributes;

    public function __construct(
        ?string      $type = null,
        public ?bool $isTargeted = null,
        mixed        ...$attributes
    ) {
        $this->type = $type ?? $attributes['alias'] ?? null;

        $attributes['type'] = $this->type;
        $attributes['isTargeted'] = $isTargeted;

        $this->attributes = $attributes;
    }
}
