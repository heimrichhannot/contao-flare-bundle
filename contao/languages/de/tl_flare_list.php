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
###< data_container_legend ###
