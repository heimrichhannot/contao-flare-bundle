<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

use Symfony\Component\Form\FormTypeInterface;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsFilterElement
{
    public const TAG = 'huh.flare.filter_element';

    public array $attributes;

    /**
     * @param ?string $type
     * @param ?string $palette
     * @param ?class-string<FormTypeInterface> $formType
     * @param ?string $method
     * @param bool|null $isTargeted
     * @param mixed ...$attributes
     */
    public function __construct(
        ?string $type = null,
        ?string $palette = null,
        ?string $formType = null,
        ?string $method = null,
        ?bool   $isTargeted = null,
        mixed   ...$attributes
    ) {
        $attributes['type'] = $type ?? $attributes['alias'] ?? null;
        $attributes['palette'] = $palette;
        $attributes['formType'] = $formType;
        $attributes['method'] = $method;
        $attributes['isTargeted'] = $isTargeted;

        $this->attributes = $attributes;
    }
}