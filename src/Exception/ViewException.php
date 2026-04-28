<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;

/**
 * @internal This exception is thrown internally and should not be
 *           thrown by userland code. You may catch it, however.
 */
class ViewException extends FlareException
{
    /**
     * @param class-string<ViewInterface> $expectedClass
     */
    public static function create(string $expectedClass, mixed $var, ?string $method = null): static
    {
        $type = \is_object($var) ? \get_class($var) : \get_debug_type($var);

        return new static(
            message: \sprintf('Expected instance of %s, got %s', $expectedClass, $type),
            method: $method
        );
    }
}