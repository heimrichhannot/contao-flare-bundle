<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * A component read a config key its schema does not define. This is a programming error, not a
 * filter failure: as an {@see \Error} it escapes every filter failure handling unwrapped, so a
 * misspelled key can neither be retried away nor degrade into a skipped filter.
 */
final class UndefinedConfigKeyError extends \Error
{
    /**
     * @param list<string> $defined The keys the component's schema defines.
     * @param string|null $owner The component class, if known.
     */
    public function __construct(
        public readonly string  $key,
        public readonly array   $defined,
        public readonly ?string $owner = null,
    ) {
        parent::__construct(\sprintf(
            'The config key "%s" is not defined by %s. Defined keys: %s.',
            $key,
            $owner ?? 'the component',
            $defined ? \implode(', ', $defined) : 'none',
        ));
    }
}
