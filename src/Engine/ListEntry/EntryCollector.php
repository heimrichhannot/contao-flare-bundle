<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\ListEntry;

use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Event\ListDriverEntriesEvent;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Turns the loaded rows of a list into a sealed {@see EntryCollection}.
 */
final readonly class EntryCollector
{
    /**
     * @param ?\Closure(Entry): ?string $linker
     */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ListCompound $list,
        private IntentInterface $intent,
        private ?\Closure $linker = null,
    ) {}

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function collect(array $rows, EntryLookupInterface $lookup): EntryCollection
    {
        $entries = EntryCollection::forTable($this->list->dc, $rows, $this->linker);

        if (\count($entries) > 0) {
            $this->eventDispatcher->dispatch(new ListDriverEntriesEvent($this->list, $this->intent, $entries, $lookup));
        }

        $entries->seal();

        return $entries;
    }
}
