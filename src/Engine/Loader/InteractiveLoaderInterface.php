<?php

namespace HeimrichHannot\FlareBundle\Engine\Loader;

interface InteractiveLoaderInterface
{
    public function fetchEntries(): array;
}