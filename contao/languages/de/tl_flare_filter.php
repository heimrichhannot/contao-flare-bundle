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
$lang['useStart'] = ['Startdatum beachten', 'Das Startdatum des Elements beachten.'];
$lang['useStop'] = ['Enddatum beachten', 'Das Enddatum des Elements beachten.'];
$lang['field_start'] = ['Startdatum-Feld', 'Bitte wählen Sie das Feld aus, das als Startdatum verwendet werden soll.'];
$lang['field_stop'] = ['Enddatum-Feld', 'Bitte wählen Sie das Feld aus, das als Enddatum verwendet werden soll.'];
###< filter_legend ###
