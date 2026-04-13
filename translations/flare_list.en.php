<?php

use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType\EventsListType;
use HeimrichHannot\FlareBundle\ListType;

return [
    ListType\GenericDataContainerListType::TYPE => 'Data Container',
    ListType\NewsListType::TYPE => 'News',

    EventsListType::TYPE => 'Events',
];
