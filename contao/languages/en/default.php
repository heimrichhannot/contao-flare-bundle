<?php

use HeimrichHannot\FlareBundle\Controller\ContentElement\ListViewController;
use HeimrichHannot\FlareBundle\Controller\ContentElement\ReaderController;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Sort\SortOrder;

$lang = &$GLOBALS['TL_LANG'];
$flare = &$lang['FLARE'];
$err = &$lang['ERR']['flare'];

$lang['CTE'][ListViewController::TYPE] = ['List view [FLARE]', 'Displays a FLARE list.'];
$lang['CTE'][ReaderController::TYPE] = ['Detail reader [FLARE]', 'Displays the reader for a FLARE list.'];

$flare['sort_order'] = [
    SortOrder::ASC => ['Ascending [ASC]', 'Sort ascending.'],
    SortOrder::DESC => ['Descending [DESC]', 'Sort descending.'],
];

$flare['date_time'] = [
    'custom' => 'Custom',
    'date' => 'Set custom date',
    'str' => 'Date string (PHP)',

    // day
    'day'              => 'Day',
    'yesterday'        => 'Yesterday',
    'now'              => 'Current time',
    'today'            => 'Today',
    'tomorrow'         => 'Tomorrow',

    // week
    'week'             => 'Week',
    'last_week'        => 'Last week',
    'this_week'        => 'This week',
    'next_week'        => 'Next week',

    // month
    'month'            => 'Month',
    'last_month'       => 'Last month',
    'this_month'       => 'This month',
    'next_month'       => 'Next month',

    // year
    'year'             => 'Year',
    'next_year'        => 'Next year',
    'this_year'        => 'This year',
    'last_year'        => 'Last year',

    // relative_future
    'relative_future'  => 'Relative in the future',
    'in_1_week'        => 'In 1 week',
    'in_2_weeks'       => 'In 2 weeks',
    'in_3_weeks'       => 'In 3 weeks',
    'in_1_month'       => 'In 1 month',
    'in_2_months'      => 'In 2 months',
    'in_3_months'      => 'In 3 months',
    'in_1_year'        => 'In 1 year',
    'in_2_years'       => 'In 2 years',

    // relative_past
    'relative_past'    => 'Relative in the past',
    '1_week_ago'       => '1 week ago',
    '2_weeks_ago'      => '2 weeks ago',
    '3_weeks_ago'      => '3 weeks ago',
    '1_month_ago'      => '1 month ago',
    '2_months_ago'     => '2 months ago',
    '3_months_ago'     => '3 months ago',
    '1_year_ago'       => '1 year ago',
    '2_years_ago'      => '2 years ago',
];

$err['listview']['malconfigured'] = 'This list view is misconfigured.';
$err['tl_content'][ContentContainer::FIELD_FORM_NAME] = 'Must start with a letter and may only contain a-z, 0-9, _. Must not end with _page.';
