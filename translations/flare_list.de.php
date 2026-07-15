<?php

use HeimrichHannot\FlareBundle\List\Type;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType\EventsListType;

return [
    Type\GenericDataContainerListDriver::TYPE => 'Data-Container',
    Type\NewsListDriver::TYPE => 'Nachrichten',

    EventsListType::TYPE => 'Events',
];
