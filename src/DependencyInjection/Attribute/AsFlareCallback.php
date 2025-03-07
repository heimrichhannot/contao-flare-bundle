<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

/**
 * An A
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AsFlareCallback
{
    /**
     * @param string $element The filter element alias. Do not use the fully qualified class name.
     * @param string $target The target callback name.
     * @param string|null $method The method name of the callback if the target is a class.
     * @param int|null $priority The priority of the callback.
     */
    public function __construct(
        public string  $element,
        public string  $target,
        public ?string $method = null,
        public ?int    $priority = null
    ) {}
}