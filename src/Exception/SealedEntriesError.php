<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * An attachment was registered after the entry collection was sealed.
 */
final class SealedEntriesError extends \Error
{
    public function __construct(public readonly string $name)
    {
        parent::__construct(\sprintf(
            'Cannot attach "%s": the entries are sealed, no attachment can be added after the entries event.',
            $name,
        ));
    }
}
