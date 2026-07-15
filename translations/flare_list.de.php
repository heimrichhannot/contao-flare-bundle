<?php

use HeimrichHannot\FlareBundle\List\Driver;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListDriver\EventsListDriver;

return [
    Driver\GenericDataContainerListDriver::TYPE => 'Data-Container',
    Driver\NewsListDriver::TYPE => 'Nachrichten',

    EventsListDriver::TYPE => 'Events',
];
