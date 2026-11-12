<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per non-empty entry collection, before it is sealed. Listeners attach values to the entries —
 * also per type via the named event `flare.list_driver.{type}.entries`.
 */
class ListDriverEntriesEvent extends Event
{
    public function __construct(
        public readonly ListCompound $list,
        public readonly IntentInterface $intent,
        public readonly EntryCollection $entries,
        private readonly EntryLookupInterface $lookup,
    ) {}

    /**
     * Proxies {@see EntryCollection::attach()}; phase 1 gets the lookup as second argument.
     *
     * @param \Closure(EntryCollection, EntryLookupInterface): (\Closure(Entry): mixed) $load
     */
    public function attach(string $name, \Closure $load): void
    {
        $lookup = $this->lookup;

        $this->entries->attach(
            $name,
            static fn (EntryCollection $entries): \Closure => $load($entries, $lookup),
        );
    }
}
