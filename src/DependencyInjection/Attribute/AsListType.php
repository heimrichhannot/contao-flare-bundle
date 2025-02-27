<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

use HeimrichHannot\FlareBundle\Util\Str;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsListType
{
    public array $attributes;

    public function __construct(?string $alias = null, ...$attributes)
    {
        $attributes['alias'] = $alias;

        $this->attributes = $attributes;
    }
}