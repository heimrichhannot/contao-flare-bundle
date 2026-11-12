<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Entry;

interface EntryTaggerInterface
{
    public function tag(Entry ...$entries): void;
}
