<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AsFilterInvoker
{
    /**
     * @param string|null $filterType The type of the filter element (e.g., 'flare_bool').
     *                                If null, it is inferred from the class, which must be a filter element service.
     * @param string|null $context    The context this invoker applies to (e.g., 'interactive').
     *                                If null, it's the default invoker for contexts without a specific one.
     * @param string|null $method     The public method to be called on the service.
     *                                Required when used on a class, unless the method is `__invoke`.
     * @param int         $priority   Higher priority invokers are chosen over lower priority ones for the same
     *                                filter type and context.
     */
    public function __construct(
        public ?string $filterType = null,
        public ?string $context = null,
        public ?string $method = null,
        public int $priority = 0,
    ) {}
}