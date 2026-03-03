<?php

$dca = &$GLOBALS['TL_DCA']['tl_calendar_events'];

$dca['fields']['_flare_event_group'] = [
    /**
     * Add an anonymous, non-database field to store the temporary event group calculated by the list item provider.
     */
    'exclude' => true,
    'inputType' => 'text',
    'default' => '',
];
