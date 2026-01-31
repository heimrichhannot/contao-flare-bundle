<?php

namespace HeimrichHannot\FlareBundle\Contract\ListType;

use HeimrichHannot\FlareBundle\Event\ListQueryPrepareEvent;

interface PrepareListQueryInterface
{
    public function onListQueryPrepareEvent(ListQueryPrepareEvent $event): void;
}