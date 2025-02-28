<?php

use HeimrichHannot\FlareBundle\Model\ListModel;

$lang = &$GLOBALS['TL_LANG'][ListModel::getTable()];

###> title_legend ###
$lang['title_legend'] = 'General Settings';
$lang['title'] = ['Title', 'Please enter a title for this list.'];
$lang['type'] = ['Type', 'Please choose the type of this list.'];
###< title_legend ###

###> publish_legend ###
$lang['publish_legend'] = 'Publishing Settings';
$lang['published'] = ['Published', 'Whether content elements with this list should be rendered.'];
###< publish_legend ###

###> data_container_legend ###
$lang['data_container_legend'] = 'Data Container Settings';
$lang['dc'] = ['Data-Container', 'Please choose the data container on whose entries the list should be based.'];
###< data_container_legend ###
