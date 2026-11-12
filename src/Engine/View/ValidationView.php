<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\View;

use HeimrichHannot\FlareBundle\Engine\ListEntry\EntryCollector;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\InvalidLookupIdError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\Reader\BackLink;

class ValidationView implements ViewInterface
{
    /** @var array<string, ?Entry> the entries by auto-item, misses as null */
    private array $entriesByAutoItem = [];

    /**
     * @param ValidationLoaderInterface $loader Loads the member row of an auto-item, by the reader's rules.
     * @param EntryCollector $collector Turns the row into an entry that links to the reader.
     * @param EntryLookupInterface $lookup Member entries of the list by the reader's rules.
     * @param BackLink|null $backLink The link back to the list view, if any.
     */
    public function __construct(
        private readonly ValidationLoaderInterface $loader,
        private readonly EntryCollector            $collector,
        private readonly EntryLookupInterface      $lookup,
        private readonly ?BackLink                 $backLink = null,
    ) {}

    public function getBackLink(): ?BackLink
    {
        return $this->backLink;
    }

    /**
     * The member entry whose auto-item field holds $autoItem, from a one-entry collection with the linker: `entry.to`
     * is the canonical reader URL, the entries event ran, the collection is sealed. Memoized per auto-item, misses
     * too.
     *
     * @throws FlareException
     */
    public function getEntryByAutoItem(string $autoItem): ?Entry
    {
        if (!\array_key_exists($autoItem, $this->entriesByAutoItem))
        {
            $row = $this->loader->fetchEntryByAutoItem($autoItem);

            $this->entriesByAutoItem[$autoItem] = $row === null
                ? null
                : $this->collector->collect([$row], $this->lookup)->first();
        }

        return $this->entriesByAutoItem[$autoItem];
    }

    /**
     * Member entries of the list by id, by the reader's rules. Twig: `flare_reader.lookup(ids)`. `mixed` for the
     * reason given at {@see EntryLookupInterface::lookup()}.
     *
     * @param int|string|list<int|string> $ids
     * @return ($ids is array ? EntryCollection : ?Entry)
     *
     * @throws InvalidLookupIdError
     * @throws FlareException
     */
    public function lookup(mixed $ids): Entry|EntryCollection|null
    {
        return $this->lookup->lookup($ids);
    }
}
