<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * A PHP read of a name that is neither reserved, a row column nor an attachment.
 */
final class UndefinedEntryKeyError extends \Error
{
    public function __construct(public readonly string $name)
    {
        parent::__construct(\sprintf(
            'The entry has no column or attachment "%s". Columns are reached through entry.row.',
            $name,
        ));
    }
}
