<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsListDriver
{
    public const TAG = 'huh.flare.list_type';

    public ?string $type;
    public array $attributes;

    public function __construct(
        ?string                  $type = null,
        public string|array|null $dataContainer = null,
        mixed                    ...$attributes
    ) {
        $this->type = $type ?? $attributes['alias'] ?? null;

        $attributes['type'] = $this->type;
        $attributes['dataContainer'] = $dataContainer;

        $this->attributes = $attributes;
    }
}
