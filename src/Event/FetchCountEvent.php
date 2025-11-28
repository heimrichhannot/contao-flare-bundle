<?php

namespace HeimrichHannot\FlareBundle\Event;

class FetchCountEvent extends AbstractFetchEvent
{
    public function getEventName(): string
    {
        return "flare.list.{$this->getListModel()->type}.fetch_count";
    }
}