<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Dto\FetchSingleEntryConfig;

class FetchListEntriesEvent extends AbstractFetchEvent implements FlareDynamicEventInterface
{
    private ?FetchSingleEntryConfig $singleEntryConfig = null;

    public function isSingle(): bool
    {
        return (bool) $this->singleEntryConfig;
    }

    public function getSingleEntryConfig(): ?FetchSingleEntryConfig
    {
        return $this->singleEntryConfig;
    }

    public function withSingleEntryConfig(FetchSingleEntryConfig $singleEntryConfig): static
    {
        $clone = clone $this;
        $clone->singleEntryConfig = $singleEntryConfig;
        return $clone;
    }

    public function getEventName(): string
    {
        return "flare.list.{$this->getListModel()->type}.fetch_entries";
    }
}