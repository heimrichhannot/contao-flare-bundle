<?php

use HeimrichHannot\FlareBundle\Filter\Control\CheckboxFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\ChoiceBoolFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\ChoiceFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\DateRangeFilterControl;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Model\FilterModel;

$lang = &$GLOBALS['TL_LANG'][FilterModel::getTable()];

###> title_legend ###
$lang['title_legend'] = 'Allgemein';
$lang['title'] = ['Titel', 'Bitte geben Sie einen Titel für diesen Listenfilter ein.'];
$lang['type'] = ['Typ', 'Bitte wählen Sie den Typ der Liste aus.'];
$lang['control'] = ['Bedienelement', 'Legt fest, wie dieser Filter dargestellt wird. Leer lassen, um ihn unsichtbar anzuwenden (intrinsisch).'];
$lang['value'] = ['Wert', 'Der feste Wert des Filters bzw. der Startwert seines Bedienelements.'];
$lang['control_options'] = [
    ChoiceFilterControl::TYPE => 'Auswahl',
    CheckboxFilterControl::TYPE => 'Checkbox',
    ChoiceBoolFilterControl::TYPE => 'Ja/Nein-Auswahl',
    DateRangeFilterControl::TYPE => 'Datumsbereich',
    KeywordsFilterControl::TYPE => 'Stichwortsuche',
];
$lang['targetAlias'] = ['Anwenden auf', 'Wählen Sie, auf welche Relation dieser Filter angewendet werden soll.'];
###< title_legend ###

###> control_legend ###
$lang['control_legend'] = 'Bedienelement-Einstellungen';
###< control_legend ###

###> publish_legend ###
$lang['publish_legend'] = 'Veröffentlichung';
$lang['published'] = ['Veröffentlicht', 'Ob der Filter aktiv ist.'];
###< publish_legend ###

###> filter_legend ###
$lang['filter_legend'] = 'Filter-Einstellungen';
$lang['elm_usePublished'] = ['Veröffentlichungsstatus beachten', 'Beachte ein Feld mit dem Veröffentlichungsstatus.'];
$lang['elm_useStart'] = ['Startdatum beachten', 'Das Startdatum des Elements beachten.'];
$lang['elm_useStop'] = ['Enddatum beachten', 'Das Enddatum des Elements beachten.'];
$lang['useTablePtable'] = ['Statische Archiv-Tabelle festlegen', 'Ignoriere andere Einstellungen und lege eine Archivtabelle statisch fest.'];
$lang['elm_invertPublished'] = ['Veröffentlicht invertieren', 'Aktivieren, wenn das Feld Einträge als versteckt markiert.'];
$lang['elm_fieldPublished'] = ['Veröffentlicht-Feld', 'Bitte wählen Sie das Feld mit dem Veröffentlichungsstatus aus.'];
$lang['elm_fieldStart'] = ['Startdatum-Feld', 'Bitte wählen Sie das Feld aus, das als Startdatum verwendet werden soll.'];
$lang['elm_fieldStop'] = ['Enddatum-Feld', 'Bitte wählen Sie das Feld aus, das als Enddatum verwendet werden soll.'];
$lang['elm_fieldPid'] = ['Eltern-ID-Feld', 'Bitte wählen Sie das Feld aus, das die ID der Elternentität enthält.'];
$lang['elm_fieldPtable'] = ['Elterntabelle-Feld', 'Bitte wählen Sie das Feld aus, das den Tabellennamen der Elternentität enthält.'];
$lang['elm_fieldGeneric'] = ['Feld', 'Bitte wählen Sie das Feld aus, welches der Filter verwendet.'];
$lang['elm_columnsGeneric'] = ['Spalten', 'Bitte wählen Sie die Spalten aus, die der Filter verwenden soll.'];
$lang['elm_tablePtable'] = ['Elterntabelle', 'Bitte wählen Sie die Tabelle aus, die die Elternentität darstellt.'];
$lang['elm_whichPtable'] = ['Elterntabelle ermitteln', 'Wenn möglich, Elternentität automatisch ermitteln oder manuell festlegen.'];
$lang['elm_whichPtable_options'] = [
    'auto' => 'Elterntabelle automatisch ermitteln',
    'dynamic' => 'Spalte mit dynamischer Elterntabelle festlegen',
    'static' => 'Statische Elterntabelle festlegen',
];
###< filter_legend ###

###> date_legend ###
$lang['date_legend'] = 'Datumseinstellungen';
$lang['date_start_legend'] = 'Startdatumseinstellungen';
$lang['date_stop_legend'] = 'Enddatumseinstellungen';
$lang['elm_configureStart'] = ['Startdatum konfigurieren', 'Bitte wählen Sie die Konfiguration für das Startdatum aus.'];
$lang['elm_configureStop'] = ['Enddatum konfigurieren', 'Bitte wählen Sie die Konfiguration für das Enddatum aus.'];
$lang['elm_startAt'] = ['Startdatum', 'Bitte geben Sie ein Startdatum ein.'];
$lang['elm_stopAt'] = ['Enddatum', 'Bitte geben Sie ein Enddatum ein.'];
$lang['elm_hasExtendedEvents'] = ['Veranstaltungen erweitern', 'Alle Veranstaltungen im Zeitraum zeigen, auch wenn sie davor beginnen und den Zeitraum überlagern.'];
###< date_legend ###

###> archive_legend ###
$lang['archive_legend'] = 'Archiv-Einstellungen';
$lang['elm_whitelistParents'] = ['Eltern-Whitelist', 'Bitte wählen Sie die Archive aus, die für diesen Filter zulässig sind.'];
$lang['elm_groupWhitelistParents'] = ['Zulässige Eltern-Archive definieren', 'Bitte wählen Sie die Tabellen und Archive aus, die für diesen Filter zulässig sind.'];
$lang['elm_groupWhitelistParents_'] = [
    'tablePtable' => ['Elterntabelle', 'Bitte wählen Sie die Tabelle aus, die die Elternentität darstellt.'],
    'whitelistParents' => ['Eltern-Whitelist', 'Bitte wählen Sie die Archive aus, die für diesen Filter zulässig sind.'],
    'formatLabel' => ['Formatierung', 'Bitte wählen Sie eine Formatierung für die Anzeige.'],
    'formatLabel_custom' => 'Eigene Formatierung',
    'formatLabelCustom' => ['Eigene Formatierung', 'Bitte geben Sie eine Formatierung für die Anzeige ein.'],
];
$lang['elm_useWhitelistForOptionsOnly'] = ['Whitelist nur auf Formularoptionen anwenden', 'Aktiv: Whitelist begrenzt nur die im Filter angezeigten Archive. Ohne Auswahl im Formular werden auch Elemente aus anderen Archiven angezeigt.'];
###< archive_legend ###

###> form_legend ###
$lang['form_legend'] = 'Formular-Einstellungen';
$lang['ctl_isMandatory'] = ['Pflichtfeld', 'Das Feld muss ausgefüllt werden.'];
$lang['ctl_isMultiple'] = ['Mehrfachauswahl', 'Erlaube die Auswahl mehrerer Elemente.'];
$lang['ctl_isExpanded'] = ['Auswahl expandieren', 'Zeige alle Elemente auf einmal in einer Liste mit Checkboxen.'];
$lang['elm_isLimited'] = ['Eingeschränkte Auswahl', 'Die Auswahl durch gegebene Optionen einschränken.'];
$lang['ctl_formatLabel'] = ['Formatierung', 'Bitte wählen Sie eine Formatierung für die Anzeige.'];
$lang['ctl_formatLabel_custom'] = 'Eigene Formatierung';
$lang['ctl_formatLabelCustom'] = ['Eigene Formatierung', 'Bitte geben Sie eine Formatierung für die Anzeige ein.'];
$lang['ctl_hasEmptyOption'] = ['Leere Option hinzufügen', 'Füge eine leere Option am Anfang der Liste hinzu.'];
$lang['ctl_formatEmptyOption'] = ['Leere Option anzeigen als', 'Bitte wählen Sie eine Formatierung für die leere Option aus.'];
$lang['ctl_formatEmptyOption_custom'] = 'Eigene Formatierung';
$lang['ctl_formatEmptyOptionCustom'] = ['Formatierung der leeren Option', 'Bitte geben Sie einen Text oder ein Labelformat für die leere Option ein.'];
$lang['ctl_placeholder'] = ['Platzhalter', 'Bitte geben Sie einen Platzhaltertext ein.'];
$lang['ctl_label'] = ['Label', 'Bitte geben Sie ein Label für das Feld ein.'];
$lang['ctl_boolBinaryChoices'] = ['Auswahlwerte', 'Bitte wählen Sie die Werte, für die die Checkbox steht.'];
###< form_legend ###

###> flare_simple_equation_legend ###
$lang['flare_simple_equation_legend'] = 'Gleichungseinstellungen';
$lang['elm_equationOperator'] = ['Operator', 'Operator der Gleichung aus.'];
$lang['elm_equationLeft'] = ['Linker Operand', 'Bitte wählen Sie den linken Operanden für die Gleichung aus.'];
$lang['elm_equationRight'] = ['Rechter Operand', 'Bitte wählen Sie den rechten Operanden für die Gleichung aus.'];
###< flare_simple_equation_legend ###

###> expert_legend ###
$lang['expert_legend'] = 'Experten-Einstellungen';
$lang['alias'] = ['Formularfeld-Alias', 'Geben Sie einen Alias für das Formularfeld dieses Filters ein.'];
###< expert_legend ###
