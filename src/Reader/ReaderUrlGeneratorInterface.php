<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader;

use Contao\Model;

interface ReaderUrlGeneratorInterface
{
    public function generate(Model $model): ?string;
}