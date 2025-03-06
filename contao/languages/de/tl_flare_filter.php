<?php

use HeimrichHannot\FlareBundle\Model\FilterModel;

$lang = &$GLOBALS['TL_LANG'][FilterModel::getTable()];

###> title_legend ###
$lang['title_legend'] = 'Allgemein';
$lang['title'] = ['Titel', 'Bitte geben Sie einen Titel für diesen Listenfilter ein.'];
$lang['type'] = ['Typ', 'Bitte wählen Sie den Typ der Liste aus.'];
$lang['intrinsic'] = ['Intrinsisch', 'Diesen Filter immer anwenden und vor dem Benutzer verstecken.'];
###< title_legend ###

###> publish_legend ###
$lang['publish_legend'] = 'Veröffentlichung';
$lang['published'] = ['Veröffentlicht', 'Ob der Filter aktiv ist.'];
###< publish_legend ###

###> filter_legend ###
$lang['filter_legend'] = 'Filter-Einstellungen';
$lang['usePublished'] = ['Veröffentlichungsstatus beachten', 'Beachte ein Feld mit dem Veröffentlichungsstatus.'];
$lang['useStart'] = ['Startdatum beachten', 'Das Startdatum des Elements beachten.'];
$lang['useStop'] = ['Enddatum beachten', 'Das Enddatum des Elements beachten.'];
$lang['useTablePtable'] = ['Statische Archiv-Tabelle festlegen', 'Ignoriere andere Einstellungen und lege eine Archivtabelle statisch fest.'];
$lang['invertPublished'] = ['Veröffentlicht invertieren', 'Aktivieren, wenn das Feld Einträge als versteckt markiert.'];
$lang['fieldPublished'] = ['Veröffentlicht-Feld', 'Bitte wählen Sie das Feld mit dem Veröffentlichungsstatus aus.'];
$lang['fieldStart'] = ['Startdatum-Feld', 'Bitte wählen Sie das Feld aus, das als Startdatum verwendet werden soll.'];
$lang['fieldStop'] = ['Enddatum-Feld', 'Bitte wählen Sie das Feld aus, das als Enddatum verwendet werden soll.'];
$lang['fieldPid'] = ['Eltern-ID-Feld', 'Bitte wählen Sie das Feld aus, das die ID der Elternentität enthält.'];
$lang['fieldPtable'] = ['Elterntabelle-Feld', 'Bitte wählen Sie das Feld aus, das den Tabellennamen der Elternentität enthält.'];
$lang['tablePtable'] = ['Elterntabelle', 'Bitte wählen Sie die Tabelle aus, die die Elternentität darstellt.'];
$lang['whichPtable'] = ['Elterntabelle ermitteln', 'Bitte wählen Sie die Tabelle aus, die die Elternentität darstellt.'];
$lang['whichPtable_options'] = [
    'auto' => 'Elterntabelle automatisch ermitteln',
    'dynamic' => 'Spalte mit dynamischer Elterntabelle festlegen',
    'static' => 'Statische Elterntabelle festlegen',
];
###< filter_legend ###

###> archive_legend ###
$lang['archive_legend'] = 'Archiv-Einstellungen';
$lang['whitelistParents'] = ['Eltern-Whitelist', 'Bitte wählen Sie die Archive aus, die für diesen Filter zulässig sind.'];
$lang['groupWhitelistParents'] = ['Zulässige Eltern-Archive definieren', 'Bitte wählen Sie die Tabellen und Archive aus, die für diesen Filter zulässig sind.'];
###< archive_legend ###
