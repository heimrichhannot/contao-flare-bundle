<?php

use HeimrichHannot\FlareBundle\Controller\ContentElement\ListViewController;
use HeimrichHannot\FlareBundle\Controller\ContentElement\ReaderController;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;

$lang = &$GLOBALS['TL_LANG'];
$flare = &$lang['FLARE'];
$err = &$lang['ERR']['flare'];

$lang['CTE'][ListViewController::TYPE] = ['FLARE Listenansicht', 'Zeigt eine FLARE-Liste an.'];
$lang['CTE'][ReaderController::TYPE] = ['FLARE Detailleser', 'Zeigt den Leser zu einer FLARE-Liste an.'];

$flare['filter'] = [
    'flare_archive' => ['Archiv', 'Filtern nach Archiv.'],
    'flare_published' => ['Veröffentlicht', 'Nur veröffentlichte Elemente anzeigen.'],
    'flare_relation_belongsTo' => ['Relation: Gehört zu', 'Filtern nach zugehörigen Eltern-Entitäten.'],
];

$flare['list'] = [
    'flare_generic_dc' => ['Data-Container', 'Listet Elemente eines Data-Containers auf.'],
    'flare_news' => ['Nachrichten', 'Listet Nachrichten auf.'],
];

$err['listview']['malconfigured'] = 'Diese Listenansicht ist fehlerhaft konfiguriert.';
$err['tl_content'][ContentContainer::FIELD_FORM_NAME] = 'Muss mit einem Buchstaben beginnen und darf nur a-z, 0-9, _ beinhalten. Darf nicht mit _page enden.';
