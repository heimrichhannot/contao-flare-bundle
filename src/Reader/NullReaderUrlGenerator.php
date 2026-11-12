<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader;

use HeimrichHannot\FlareBundle\List\Entry\Entry;

class NullReaderUrlGenerator implements ReaderUrlGeneratorInterface
{
    public function generate(Entry $entry): ?string
    {
        return null;
    }
}
