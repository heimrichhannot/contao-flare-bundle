<?php

use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;

$lang = &$GLOBALS['TL_LANG']['tl_content'];

$lang['flare_list_legend'] = 'List Settings';
$lang[ContentContainer::FIELD_FORM_NAME] = ['Form Name', 'Enter the name of the filter form.'];
$lang[ContentContainer::FIELD_ITEMS_PER_PAGE] = ['Items per Page', 'Enter the number of items per page. Set to 0 to show all.'];
$lang[ContentContainer::FIELD_JUMP_TO] = ['Form Redirect', 'Choose the "action" page to which the form will redirect upon submission.'];
$lang[ContentContainer::FIELD_LIST] = ['List', 'Please choose the FLARE list to display.'];
