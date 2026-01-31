<?php

use HeimrichHannot\FlareBundle\Model\ListModel;

$lang = &$GLOBALS['TL_LANG'][ListModel::getTable()];

###> title_legend ###
$lang['title_legend'] = 'Grundlegende Einstellungen';
$lang['title'] = ['Titel', 'Bitte geben Sie einen Titel für diese Liste ein.'];
$lang['type'] = ['Typ', 'Bitte wählen Sie den Typ dieser Liste.'];
###< title_legend ###

###> publish_legend ###
$lang['publish_legend'] = 'Veröffentlichungseinstellungen';
$lang['published'] = ['Veröffentlicht', 'Ob Inhaltselemente mit dieser Liste gerendert werden sollen.'];
###< publish_legend ###

###> data_container_legend ###
$lang['data_container_legend'] = 'DC-Einstellungen';
$lang['dc'] = ['Data-Container', 'Bitte wählen Sie den Data-Container, auf dessen Einträgen die Liste basieren soll.'];
$lang['fieldAutoItem'] = ['Auto-Item-Feld', 'Bitte wählen Sie das Feld aus, das als Auto-Item verwendet werden soll.'];
$lang['dcMultilingual_display'] = ['Mehrsprachige darstellung', 'Bitte wählen Sie, wie mehrsprachige Einträge in der Liste dargestellt werden sollen.'];
###< data_container_legend ###

###> flare_defaults_legend ###
$lang['flare_defaults_legend'] = 'Standardwerte';
$lang['sortSettings'] = ['Standard-Sortierung', 'Hier können Sie die standardmäßige Sortierung der Liste anpassen.'];
$lang['sortSettings__column'] = ['Spalte', 'Bitte wählen Sie die Spalte, nach der die Liste standardmäßig sortiert werden soll.'];
$lang['sortSettings__direction'] = ['Sortierreihenfolge', 'Bitte wählen Sie die Sortierreihenfolge.'];
###< flare_defaults_legend ###

###> flare_reader_legend ###
$lang['flare_reader_legend'] = 'Leser-Einstellungen';
$lang['jumpToReader'] = ['Detailleserseite', 'Bitte wählen Sie die Seite aus, zu der Besucher weitergeleitet werden, wenn sie auf einen Listeneintrag klicken.'];
###< flare_reader_legend ###

###> meta_legend ###
$lang['meta_legend'] = 'Meta-Einstellungen';
$lang['metaTitleFormat'] = ['Meta-Titel-Format', 'Verwenden Sie Simple-Tokens um Felder des Data-Containers als Titel auszugeben.'];
$lang['metaDescriptionFormat'] = ['Meta-Beschreibungs-Format', 'Verwenden Sie Simple-Tokens um Felder des Data-Containers als Beschreibung auszugeben.'];
$lang['metaRobotsFormat'] = ['Meta-Robots-Format', 'Geben Sie ein statisches Robots-Attribut an oder verwenden Sie Simple-Tokens um Felder des Containers dafür auszugeben.'];
###< meta_legend ###

###> parent_legend
$lang['parent_legend'] = 'Eltern-Einstellungen';
$lang['hasParent'] = ['Eltern-Relation verfügbar', 'Aktivieren, damit Filter nach Archiveigenschaften filtern können.'];
$lang['fieldPid'] = ['Eltern-ID-Feld', 'Bitte wählen Sie das Feld aus, das die ID der Elternentität enthält.'];
$lang['fieldPtable'] = ['Elterntabelle-Feld', 'Bitte wählen Sie das Feld aus, das den Tabellennamen der Elternentität enthält.'];
$lang['tablePtable'] = ['Elterntabelle', 'Bitte wählen Sie die Tabelle aus, die die Elternentität darstellt.'];
$lang['whichPtable'] = ['Elterntabelle ermitteln', 'Wenn möglich, Elternentität automatisch ermitteln oder manuell festlegen.'];
$lang['whichPtable_options'] = [
    'auto' => 'Elterntabelle automatisch ermitteln',
    'dynamic' => 'Spalte mit dynamischer Elterntabelle festlegen',
    'static' => 'Statische Elterntabelle festlegen',
];
###< parent_legend

###> advanced_legend
$lang['advanced_legend'] = 'Erweiterte Einstellungen';
###< advanced_legend

###> comments_legend ###
$lang['comments_legend'] = 'Kommentareinstellungen';
$lang['comments_enabled'] = ['Kommentare aktivieren', 'Aktivieren Sie die Kommentarfunktion für Einträge dieser Liste.'];
$lang['comments_sendNativeEmails'] = ['Standard-Benachrichtigungen versenden', 'Versendet die konfigurierten E-Mail-Benachrichtigungen von Contao Comments.'];
###< comments_legend ###
