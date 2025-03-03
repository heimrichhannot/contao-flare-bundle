<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

use Symfony\Component\Form\FormTypeInterface;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsFilterElement
{
    public array $attributes;

    /**
     * @param string $alias
     * @param class-string<FormTypeInterface>|null $formType
     * @param string $filterMethod
     */
    public function __construct(
        string $alias,
        ?string $palette = null,
        ?string $formType = null,
        ?string $filterMethod = null,
        ...$attributes
    ) {
        $attributes['alias'] = $alias;
        $attributes['palette'] = $palette;
        $attributes['formType'] = $formType;
        $attributes['filterMethod'] = $filterMethod;

        $this->attributes = $attributes;
    }
}