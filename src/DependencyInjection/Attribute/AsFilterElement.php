<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

use Symfony\Component\Form\FormTypeInterface;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsFilterElement
{
    public array $attributes;

    /**
     * @param string                           $alias
     * @param ?string                          $palette
     * @param ?class-string<FormTypeInterface> $formType
     * @param ?string                          $method
     * @param ?string[]                        $scopes  Where the filter should be applied, e.g. 'list', 'reader'.
     */
    public function __construct(
        string  $alias,
        ?string $palette = null,
        ?string $formType = null,
        ?string $method = null,
        ?array  $scopes = null,
        ?bool   $isTargeted = null,
        mixed   ...$attributes
    ) {
        $attributes['alias'] = $alias;
        $attributes['palette'] = $palette;
        $attributes['formType'] = $formType;
        $attributes['method'] = $method;
        $attributes['scopes'] = $scopes;
        $attributes['isTargeted'] = $isTargeted;

        $this->attributes = $attributes;
    }
}