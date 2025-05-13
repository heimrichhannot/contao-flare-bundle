<?php

use HeimrichHannot\FlareBundle\Controller\ContentElement\ListViewController;
use HeimrichHannot\FlareBundle\Controller\ContentElement\ReaderController;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Filter\Element;
use HeimrichHannot\FlareBundle\List\Type;
use HeimrichHannot\FlareBundle\SortDescriptor\Order;

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
    Element\SimpleEquationElement::TYPE => ['Einfache Gleichung', 'Filtern, ob ein Feld einer einfachen Gleichung entspricht.'],
    Element\SearchKeywordsElement::TYPE => ['Stichwortsuche', 'Filtern nach Freitexteingabe.'],
];

$flare['list'] = [
    Type\GenericDataContainerList::TYPE => ['Data-Container', 'Listet Elemente eines Data-Containers auf.'],
    Type\NewsListType::TYPE => ['Nachrichten', 'Listet Nachrichten auf.'],
    Type\EventsList::TYPE => ['Events', 'Listet Events auf.'],
];

$flare['sort_order'] = [
    Order::ASC => ['Aufsteigend [ASC]', 'Sortierung aufsteigend.'],
    Order::DESC => ['Absteigend [DESC]', 'Sortierung absteigend.'],
];

$err['listview']['malconfigured'] = 'Diese Listenansicht ist fehlerhaft konfiguriert.';
$err['tl_content'][ContentContainer::FIELD_FORM_NAME] = 'Muss mit einem Buchstaben beginnen und darf nur a-z, 0-9, _ beinhalten. Darf nicht mit _page enden.';
