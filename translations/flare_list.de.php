<?php

use HeimrichHannot\FlareBundle\List\Type;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType\EventsListType;

return [
    Type\GenericDataContainerListType::TYPE => 'Data-Container',
    Type\NewsListType::TYPE => 'Nachrichten',

    EventsListType::TYPE => 'Events',
];
