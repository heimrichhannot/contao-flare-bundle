<?php

use HeimrichHannot\FlareBundle\Controller\ContentElement\ListViewController;

$lang = &$GLOBALS['TL_LANG'];

$lang['CTE'][ListViewController::TYPE] = ['FLARE Listenansicht', 'Zeigt eine FLARE-Liste an.'];

$lang['FLARE']['FILTER'] = [
    'flare_published' => ['Veröffentlicht', 'Nur veröffentlichte Elemente anzeigen.'],
    'flare_relation_belongsTo' => ['Relation: Gehört zu', 'Filtern nach zugehörigen Eltern-Entitäten.'],
];

$lang['FLARE']['LIST'] = [
    'flare_generic_dc' => ['Data-Container', 'Listet Elemente eines Data-Containers auf.'],
    'flare_news' => ['Nachrichten', 'Listet Nachrichten auf.'],
];
