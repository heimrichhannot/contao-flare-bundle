<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Enum\FetchSubject;

class FetchEntriesEvent extends AbstractFetchEvent
{
    private ?int $singleEntryId = null;

    public function withSingleEntryId(int $entryId): static
    {
        $event = clone $this;
        $event->singleEntryId = $entryId;
        return $event;
    }

    public function getSingleEntryId(): ?int
    {
        return $this->singleEntryId;
    }

    public function subject(): FetchSubject
    {
        return FetchSubject::ENTRIES;
    }

    public function getEventName(): string
    {
        return "flare.list.{$this->getListModel()->type}.fetch.entries";
    }
}