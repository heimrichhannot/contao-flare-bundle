<?php

namespace HeimrichHannot\FlareBundle\Event;

class FetchCountEvent extends AbstractFetchEvent implements FlareDynamicEventInterface
{
    public function getEventName(): string
    {
        return "flare.list.{$this->getListModel()->type}.fetch_count";
    }
}