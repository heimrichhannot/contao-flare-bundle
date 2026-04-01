<?php

namespace HeimrichHannot\FlareBundle\Engine\Loader;

class InteractiveEmptyLoader implements InteractiveLoaderInterface
{
    public function fetchEntries(): array
    {
        return [];
    }
}