<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Enum\FetchSubject;

class FetchIdsEvent extends AbstractFetchEvent
{
    public function subject(): FetchSubject
    {
        return FetchSubject::IDS;
    }

    public function getEventName(): string
    {
        return "flare.list.{$this->getListModel()->type}.fetch.ids";
    }
}