<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

interface InteractiveLoaderInterface
{
    public function fetchEntries(): array;
}