<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Fixture;

use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryTaggerInterface;

/**
 * Records "<table>.<id>" of every tagged entry.
 */
final class SpyTagger implements EntryTaggerInterface
{
    /** @var list<string> */
    public array $tagged = [];

    public function tag(Entry ...$entries): void
    {
        foreach ($entries as $entry) {
            $ref = $entry->meta->modelRef;
            $this->tagged[] = $ref !== null ? $ref->table . '.' . $ref->id : '(no model)';
        }
    }
}
