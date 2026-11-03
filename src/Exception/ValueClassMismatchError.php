<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;

/**
 * A filter was given a value its element does not consume. This is a programming error, not a filter failure:
 * stored data cannot produce it (value providers and controls are registered by their narrowed return type), so the
 * value always comes from code. As an {@see \Error} it escapes every filter failure handling unwrapped and fails
 * the request.
 */
final class ValueClassMismatchError extends \Error
{
    /**
     * @param class-string<ValueInterface>|null $valueClass The value class of the element, null for none.
     * @param string $type The element type.
     * @param string|null $source Provenance of the filter, e.g. "tl_flare_filter.42".
     */
    public function __construct(
        public readonly ValueInterface $value,
        public readonly ?string        $valueClass,
        public readonly string         $type,
        public readonly ?string        $source = null,
    ) {
        parent::__construct(\sprintf(
            'The value of class "%s" does not match the value class "%s" of filter element type "%s"%s.',
            $value::class,
            $valueClass ?? 'none',
            $type,
            $source ? " ({$source})" : '',
        ));
    }
}
