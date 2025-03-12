<?php

use HeimrichHannot\FlareBundle\Controller\ContentElement\ListViewController;

$lang = &$GLOBALS['TL_LANG'];
$flare = &$lang['FLARE'];

$lang['CTE'][ListViewController::TYPE] = ['FLARE Listenansicht', 'Zeigt eine FLARE-Liste an.'];

$flare['filter'] = [
    'flare_archive' => ['Archiv', 'Filtern nach Archiv.'],
    'flare_published' => ['Veröffentlicht', 'Nur veröffentlichte Elemente anzeigen.'],
    'flare_relation_belongsTo' => ['Relation: Gehört zu', 'Filtern nach zugehörigen Eltern-Entitäten.'],
];

$flare['list'] = [
    'flare_generic_dc' => ['Data-Container', 'Listet Elemente eines Data-Containers auf.'],
    'flare_news' => ['Nachrichten', 'Listet Nachrichten auf.'],
];
