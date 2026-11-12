<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * The phase 1 loader of a collection attachment read its own name on an entry while it was still loading. This is
 * a programming error: the loader cannot depend on the value it is about to provide.
 */
final class RecursiveEntryLoadError extends \Error
{
    public function __construct(public readonly string $name)
    {
        parent::__construct(\sprintf(
            'Cannot load "%s": its collection loader read "%s" on an entry while it was still loading.',
            $name,
            $name,
        ));
    }
}
