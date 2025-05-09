<?php

use HeimrichHannot\FlareBundle\Controller\ContentElement\ListViewController;
use HeimrichHannot\FlareBundle\Controller\ContentElement\ReaderController;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Filter\Element;

$lang = &$GLOBALS['TL_LANG'];
$flare = &$lang['FLARE'];
$err = &$lang['ERR']['flare'];

$lang['CTE'][ListViewController::TYPE] = ['FLARE Listenansicht', 'Zeigt eine FLARE-Liste an.'];
$lang['CTE'][ReaderController::TYPE] = ['FLARE Detailleser', 'Zeigt den Leser zu einer FLARE-Liste an.'];

$flare['filter'] = [
    Element\ArchiveElement::TYPE => ['Archiv', 'Filtern nach Archiv.'],
    Element\PublishedElement::TYPE => ['Veröffentlicht', 'Nur veröffentlichte Elemente anzeigen.'],
    Element\BelongsToRelationElement::TYPE => ['Relation: Gehört zu', 'Filtern nach zugehörigen Eltern-Entitäten.'],
    Element\DateRangeElement::TYPE => ['Datumsbereich', 'Filtern nach einem Datumsbereich.'],
    Element\SimpleEquation::TYPE => ['Einfache Gleichung', 'Filtern, ob ein Feld einer einfachen Gleichung entspricht.'],
];

$flare['list'] = [
    'flare_generic_dc' => ['Data-Container', 'Listet Elemente eines Data-Containers auf.'],
    'flare_news' => ['Nachrichten', 'Listet Nachrichten auf.'],
    'flare_events' => ['Events', 'Listet Events auf.'],
];

$err['listview']['malconfigured'] = 'Diese Listenansicht ist fehlerhaft konfiguriert.';
$err['tl_content'][ContentContainer::FIELD_FORM_NAME] = 'Muss mit einem Buchstaben beginnen und darf nur a-z, 0-9, _ beinhalten. Darf nicht mit _page enden.';
