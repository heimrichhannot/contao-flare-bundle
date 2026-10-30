<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value;

interface ValueInterface
{
    /**
     * A string expressing the value's identity; equal values yield equal fingerprints.
     * Used for in-request caching only.
     */
    public function fingerprint(): string;
}
