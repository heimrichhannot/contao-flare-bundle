<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

use HeimrichHannot\FlareBundle\Util\Str;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsListType
{
    public array $attributes;

    public function __construct(
        ?string           $alias = null,
        string|array|null $dataContainer = null,
        string|null       $palette = null,
                          ...$attributes
    ) {
        $attributes['alias'] = $alias;
        $attributes['dataContainer'] = $dataContainer;
        $attributes['palette'] = $palette;

        $this->attributes = $attributes;
    }
}