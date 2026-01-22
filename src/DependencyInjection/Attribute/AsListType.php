<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsListType
{
    public array $attributes;

    public function __construct(
        ?string           $type = null,
        string|array|null $dataContainer = null,
        string|null       $palette = null,
        mixed             ...$attributes
    ) {
        $attributes['type'] = $type;
        $attributes['dataContainer'] = $dataContainer;
        $attributes['palette'] = $palette;

        $this->attributes = $attributes;
    }
}