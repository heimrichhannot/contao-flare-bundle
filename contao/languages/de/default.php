<?php

use HeimrichHannot\FlareBundle\Controller\ContentElement\ListViewController;
use HeimrichHannot\FlareBundle\Controller\ContentElement\ReaderController;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\FilterElement;
use HeimrichHannot\FlareBundle\ListType;
use HeimrichHannot\FlareBundle\SortDescriptor\Order;

$lang = &$GLOBALS['TL_LANG'];
$flare = &$lang['FLARE'];
$err = &$lang['ERR']['flare'];

$lang['CTE'][ListViewController::TYPE] = ['Listenansicht [FLARE]', 'Zeigt eine FLARE-Liste an.'];
$lang['CTE'][ReaderController::TYPE] = ['Detailleser [FLARE]', 'Zeigt den Leser zu einer FLARE-Liste an.'];

$flare['filter'] = [
    FilterElement\Relation\ArchiveElement::TYPE => ['Archiv', 'Filtern nach Archiv.'],
    FilterElement\Relation\BelongsToRelationElement::TYPE => ['Relation: Gehört zu', 'Filtern nach zugehörigen Eltern-Entitäten.'],
    FilterElement\BooleanElement::TYPE => ['Boolescher Eigenschaftswert', 'Filtern nach einem Boolean.'],
    FilterElement\CalendarCurrentElement::TYPE => ['Kalender-Zeitfenster', 'Filtern nach aktuellen Events.'],
    FilterElement\CodefogTagsElement::TYPE => ['Tags [codefog/tags-bundle]', 'Filtern nach Codefog Tags.'],
    FilterElement\DateRangeElement::TYPE => ['Datumsbereich', 'Filtern nach einem Datumsbereich.'],
    FilterElement\DcaSelectField::TYPE => ['DCA-Feld Optionsauswahl', 'Filtern nach einem ausgewählten DCA-Feld.'],
    FilterElement\FieldValueChoiceElement::TYPE => ['DCA-Feld Feldwerte-Auswahl (beta)', 'Filtern nach vorhandenen Feldwerten.'],
    FilterElement\PublishedElement::TYPE => ['Veröffentlicht', 'Nur veröffentlichte Elemente anzeigen.'],
    FilterElement\SimpleEquationElement::TYPE => ['Einfache Gleichung', 'Filtern, ob ein Feld einer einfachen Gleichung entspricht.'],
    FilterElement\SearchKeywordsElement::TYPE => ['Stichwortsuche', 'Filtern nach Freitexteingabe.'],
];

$flare['list'] = [
    ListType\GenericDataContainerListType::TYPE => ['Data-Container', 'Listet Elemente eines Data-Containers auf.'],
    ListType\NewsListType::TYPE => ['Nachrichten', 'Listet Nachrichten auf.'],
    ListType\EventsListType::TYPE => ['Events', 'Listet Events auf.'],
];

$flare['sort_order'] = [
    Order::ASC => ['Aufsteigend [ASC]', 'Sortierung aufsteigend.'],
    Order::DESC => ['Absteigend [DESC]', 'Sortierung absteigend.'],
];

$flare['date_time'] = [
    'custom' => 'Benutzerdefiniert',
    'date' => 'Eigenes Datum festlegen',
    'str' => 'Datum-String (PHP)',

    // day
    'day'              => 'Tag',
    'yesterday'        => 'Gestern',
    'now'              => 'Aktueller Zeitpunkt',
    'today'            => 'Heute',
    'tomorrow'         => 'Morgen',

    // week
    'week'             => 'Woche',
    'last_week'        => 'Letzte Woche',
    'this_week'        => 'Diese Woche',
    'next_week'        => 'Nächste Woche',

    // month
    'month'            => 'Monat',
    'last_month'       => 'Letzten Monat',
    'this_month'       => 'Diesen Monat',
    'next_month'       => 'Nächsten Monat',

    // year
    'year'             => 'Jahr',
    'next_year'        => 'Nächstes Jahr',
    'this_year'        => 'Dieses Jahr',
    'last_year'        => 'Letztes Jahr',

    // relative_future
    'relative_future'  => 'Relativ in der Zukunft',
    'in_1_week'        => 'In 1 Woche',
    'in_2_weeks'       => 'In 2 Wochen',
    'in_3_weeks'       => 'In 3 Wochen',
    'in_1_month'       => 'In 1 Monat',
    'in_2_months'      => 'In 2 Monaten',
    'in_3_months'      => 'In 3 Monaten',
    'in_1_year'        => 'In 1 Jahr',
    'in_2_years'       => 'In 2 Jahren',

    // relative_past
    'relative_past'    => 'Relativ in der Vergangenheit',
    '1_week_ago'       => 'Vor 1 Woche',
    '2_weeks_ago'      => 'Vor 2 Wochen',
    '3_weeks_ago'      => 'Vor 3 Wochen',
    '1_month_ago'      => 'Vor 1 Monat',
    '2_months_ago'     => 'Vor 2 Monaten',
    '3_months_ago'     => 'Vor 3 Monaten',
    '1_year_ago'       => 'Vor 1 Jahr',
    '2_years_ago'      => 'Vor 2 Jahren',
];

$err['listview']['malconfigured'] = 'Diese Listenansicht ist fehlerhaft konfiguriert.';
$err['tl_content'][ContentContainer::FIELD_FORM_NAME] = 'Muss mit einem Buchstaben beginnen und darf nur a-z, 0-9, _ beinhalten. Darf nicht mit _page enden.';
