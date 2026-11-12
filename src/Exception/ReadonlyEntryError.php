<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * A property of an entry was written or unset directly.
 */
final class ReadonlyEntryError extends \Error
{
    public function __construct(public readonly string $name)
    {
        parent::__construct(\sprintf(
            'The entry property "%s" cannot be written or unset: an entry is extended only through attach().',
            $name,
        ));
    }
}
