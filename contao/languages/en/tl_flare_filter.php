<?php

use HeimrichHannot\FlareBundle\Model\FilterModel;

$lang = &$GLOBALS['TL_LANG'][FilterModel::getTable()];

###> title_legend ###
$lang['title_legend'] = 'General';
$lang['title'] = ['Title', 'Please enter a title for this list filter.'];
$lang['type'] = ['Type', 'Please select the type of the filter.'];
$lang['intrinsic'] = ['Intrinsic', 'Always apply this filter and hide it from the user.'];
$lang['targetAlias'] = ['Apply on', 'Please select on which relation this filter should be applied.'];
###< title_legend ###

###> publish_legend ###
$lang['publish_legend'] = 'Publish';
$lang['published'] = ['Published', 'Whether the filter is active.'];
###< publish_legend ###

###> expert_legend ###
$lang['expert_legend'] = 'Expert settings';
$lang['formAlias'] = ['Form field alias', 'Enter an alias for this filter\'s form field.'];
###< expert_legend ###
