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
$lang['dc'] = ['Data Container', 'Please choose the data container on whose entries the list should be based.'];
$lang['fieldAutoItem'] = ['Auto-Item Field', 'Please choose the field that should be used as the auto-item.'];
$lang['dcMultilingual_display'] = ['Multilingual Display', 'Please choose how multilingual entries should be displayed in the list.'];
###< data_container_legend ###

###> flare_defaults_legend ###
$lang['flare_defaults_legend'] = 'Default Values';
$lang['sortSettings'] = ['Default Sorting', 'Here you can adjust the default sorting of the list.'];
$lang['sortSettings__column'] = ['Column', 'Please choose the column by which the list should be sorted by default.'];
$lang['sortSettings__direction'] = ['Sorting Direction', 'Please choose the sorting direction.'];
###< flare_defaults_legend ###

###> flare_reader_legend ###
$lang['flare_reader_legend'] = 'Reader Settings';
$lang['jumpToReader'] = ['Detail Reader Page', 'Please choose the page to which visitors are redirected when clicking on a list entry.'];
###< flare_reader_legend ###

###> meta_legend ###
$lang['meta_legend'] = 'Meta Settings';
$lang['metaTitleFormat'] = ['Meta Title Format', 'Use simple tokens to output fields of the data container as the meta title.'];
$lang['metaDescriptionFormat'] = ['Meta Description Format', 'Use simple tokens to output fields of the data container as the meta description.'];
$lang['metaRobotsFormat'] = ['Meta Robots Format', 'Specify a static robots attribute or use simple tokens to output fields of the container.'];
###< meta_legend ###

###> parent_legend ###
$lang['parent_legend'] = 'Parent Settings';
$lang['hasParent'] = ['Parent Relation Available', 'Enable this so filters can filter by archive properties.'];
$lang['fieldPid'] = ['Parent ID Field', 'Please choose the field containing the ID of the parent entity.'];
$lang['fieldPtable'] = ['Parent Table Field', 'Please choose the field containing the table name of the parent entity.'];
$lang['tablePtable'] = ['Parent Table', 'Please choose the table representing the parent entity.'];
$lang['whichPtable'] = ['Determine Parent Table', 'If possible, determine the parent entity automatically or set it manually.'];
$lang['whichPtable_options'] = [
    'auto' => 'Determine parent table automatically',
    'dynamic' => 'Set column with dynamic parent table',
    'static' => 'Set static parent table',
];
###< parent_legend ###

###> comments_legend ###
$lang['comments_legend'] = 'Comment Settings';
$lang['comments_enabled'] = ['Enable Comments', 'Enable the comment function for entries of this list.'];
$lang['comments_sendNativeEmails'] = ['Send Default Notifications', 'Sends the configured email notifications from Contao Comments.'];
###< comments_legend ###
