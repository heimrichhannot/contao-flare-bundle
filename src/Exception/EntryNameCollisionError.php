<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * An attachment name clashes with a reserved name, a row column or another attachment. This is a programming
 * error, thrown at registration, so a template can never see an ambiguous name.
 */
final class EntryNameCollisionError extends \Error
{
    public function __construct(
        public readonly string $name,
        public readonly string $reason,
    ) {
        parent::__construct(\sprintf('The entry name "%s" cannot be attached: %s.', $name, $reason));
    }
}
