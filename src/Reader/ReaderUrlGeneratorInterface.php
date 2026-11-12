<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader;

use HeimrichHannot\FlareBundle\List\Entry\Entry;

interface ReaderUrlGeneratorInterface
{
    /**
     * The reader URL of $entry, or null without one (e.g. an empty auto-item value).
     */
    public function generate(Entry $entry): ?string;
}
