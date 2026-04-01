<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader;

use Contao\Model;

class NullReaderUrlGenerator implements ReaderUrlGeneratorInterface
{
    public function generate(Model $model): ?string
    {
        return null;
    }
}