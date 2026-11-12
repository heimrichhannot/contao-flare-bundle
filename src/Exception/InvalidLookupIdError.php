<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * An entry lookup got something other than ids of the list's own table, e.g. a serialized picker value. This is a
 * programming error in the template or listener that called the lookup.
 */
final class InvalidLookupIdError extends \Error
{
    public function __construct(public readonly mixed $id)
    {
        parent::__construct(\sprintf(
            'Cannot look up %s: a lookup takes ids of the list\'s own table, as a positive int, its string without'
            . ' sign or leading zeros, or a list of those. Deserialize a stored value first.',
            self::describe($id),
        ));
    }

    private static function describe(mixed $id): string
    {
        if (\is_string($id)) {
            return \sprintf('the string "%s"', \strlen($id) > 40 ? \substr($id, 0, 40) . '...' : $id);
        }

        return \is_scalar($id) ? \get_debug_type($id) . ' ' . \var_export($id, true) : \get_debug_type($id);
    }
}
