<?php

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Enum\FetchSubject;

class FetchCountEvent extends AbstractFetchEvent
{
    public function subject(): FetchSubject
    {
        return FetchSubject::COUNT;
    }

    public function getEventName(): string
    {
        return "flare.list.{$this->getListModel()->type}.fetch.count";
    }
}