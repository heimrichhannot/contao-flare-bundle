<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsListDriver
{
    public const TAG = 'huh.flare.list_type';

    public array $attributes;

    public function __construct(
        ?string           $type = null,
        string|array|null $dataContainer = null,
        mixed             ...$attributes
    ) {
        $attributes['type'] = $type ?? $attributes['alias'] ?? null;
        $attributes['dataContainer'] = $dataContainer;

        $this->attributes = $attributes;
    }
}
