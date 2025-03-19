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
$lang['whichPtable'] = ['Elterntabelle ermitteln', 'Wenn möglich, Elternentität automatisch ermitteln oder manuell festlegen.'];
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
$lang['formatLabel'] = ['Formatierung', 'Bitte wählen Sie eine Formatierung für die Anzeige.'];
$lang['formatLabel_custom'] = 'Eigene Formatierung';
$lang['formatLabelCustom'] = ['Eigene Formatierung', 'Bitte geben Sie eine Formatierung für die Anzeige ein.'];
###< archive_legend ###

###> form_legend ###
$lang['form_legend'] = 'Formulareinstellungen';
$lang['isMandatory'] = ['Pflichtfeld', 'Das Feld muss ausgefüllt werden.'];
$lang['isMultiple'] = ['Mehrfachauswahl', 'Erlaube die Auswahl mehrerer Elemente.'];
$lang['isExpanded'] = ['Auswahl expandieren', 'Zeige alle Elemente auf einmal in einer Liste mit Checkboxen.'];
$lang['hasEmptyOption'] = ['Leere Option hinzufügen', 'Füge eine leere Option am Anfang der Liste hinzu.'];
$lang['formatEmptyOption'] = ['Leere Option anzeigen als', 'Bitte wählen Sie eine Formatierung für die leere Option aus.'];
$lang['formatEmptyOption_custom'] = 'Eigene Formatierung';
$lang['formatEmptyOptionCustom'] = ['Formatierung der leeren Option', 'Bitte geben Sie einen Text oder ein Labelformat für die leere Option ein.'];
$lang['preselect'] = ['Vorauswahl', 'Geben Sie an, welche Werte vorausgewählt sein sollen.'];
###< form_legend ###

###> flare_simple_equation_legend ###
$lang['flare_simple_equation_legend'] = 'Gleichungseinstellungen';
$lang['equationOperator'] = ['Operator', 'Operator der Gleichung aus.'];
$lang['equationLeft'] = ['Linker Operand', 'Bitte wählen Sie den linken Operanden für die Gleichung aus.'];
$lang['equationRight'] = ['Rechter Operand', 'Bitte wählen Sie den rechten Operanden für die Gleichung aus.'];
###< flare_simple_equation_legend ###
