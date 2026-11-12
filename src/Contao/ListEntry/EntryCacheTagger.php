<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\ListEntry;

use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryTaggerInterface;

/**
 * Tags the response with the records of entries, so that a page showing or linking an entry is invalidated when
 * Contao saves that record.
 */
final readonly class EntryCacheTagger implements EntryTaggerInterface
{
    /**
     * FOS registers the tagger only when cache tagging is enabled; Contao injects it optionally too.
     */
    public function __construct(
        private ?SymfonyResponseTagger $responseTagger = null,
    ) {}

    /**
     * Adds "contao.db.<table>.<id>" for every entry with a modelRef; skips the others; no-op without a tagger.
     */
    public function tag(Entry ...$entries): void
    {
        if ($this->responseTagger === null) {
            return;
        }

        $tags = [];

        foreach ($entries as $entry) {
            if ($ref = $entry->meta->modelRef) {
                $tags[] = \sprintf('contao.db.%s.%s', $ref->table, $ref->id);
            }
        }

        if ($tags !== []) {
            $this->responseTagger->addTags($tags);
        }
    }
}
