<?php

$dca = &$GLOBALS['TL_DCA']['tl_calendar_events'];

$dca['fields']['_flare_event_group'] = [
    'exclude' => true,
    'inputType' => 'text',
    'default' => '',
];
