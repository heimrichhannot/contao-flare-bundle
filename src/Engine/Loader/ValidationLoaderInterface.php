<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Loader;

interface ValidationLoaderInterface
{
    public function fetchEntryById(int $id): ?array;

    public function fetchEntryByAutoItem(string $autoItem): ?array;
}