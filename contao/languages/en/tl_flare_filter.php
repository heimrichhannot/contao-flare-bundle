<?php

use HeimrichHannot\FlareBundle\Filter\Control;
use HeimrichHannot\FlareBundle\Model\FilterModel;

$lang = &$GLOBALS['TL_LANG'][FilterModel::getTable()];

###> title_legend ###
$lang['title_legend'] = 'General';
$lang['title'] = ['Title', 'Please enter a title for this list filter.'];
$lang['type'] = ['Type', 'Please select the type of the filter.'];
$lang['control'] = ['Control', 'Choose how this filter is presented. Leave empty to apply it silently (intrinsic).'];
$lang['control_options'] = [
    Control\ChoiceFilterControl::TYPE => 'Choice',
    Control\CheckboxFilterControl::TYPE => 'Checkbox',
    Control\ChoiceBoolFilterControl::TYPE => 'Yes/No choice',
    Control\DateRangeFilterControl::TYPE => 'Date range',
    Control\KeywordsFilterControl::TYPE => 'Keyword search',
];
$lang['value'] = ['Value', 'The fixed value of the filter, or the initial value of its control.'];
$lang['targetAlias'] = ['Apply to', 'Select the relation this filter should be applied to.'];
###< title_legend ###

###> publish_legend ###
$lang['publish_legend'] = 'Publishing';
$lang['published'] = ['Published', 'Whether the filter is active.'];
###< publish_legend ###

###> expert_legend ###
$lang['expert_legend'] = 'Expert Settings';
$lang['alias'] = ['Form field alias', 'Enter an alias for this filter’s form field.'];
###< expert_legend ###

###> filter_legend ###
$lang['filter_legend'] = 'Filter Settings';
$lang['elm_usePublished'] = ['Consider published state', 'Consider a field representing the published state.'];
$lang['elm_fieldPublished'] = ['Published field', 'Please select the field representing the published state.'];
$lang['elm_invertPublished'] = ['Invert published state', 'Enable this if the field marks entries as hidden instead of visible.'];
$lang['elm_useStart'] = ['Consider start date', 'Consider the element’s start date.'];
$lang['elm_fieldStart'] = ['Start date field', 'Please select the field that should be used as the start date.'];
$lang['elm_useStop'] = ['Consider end date', 'Consider the element’s end date.'];
$lang['elm_fieldStop'] = ['End date field', 'Please select the field that should be used as the end date.'];
$lang['elm_fieldPid'] = ['Parent ID field', 'Please select the field containing the ID of the parent entity.'];
$lang['elm_whichPtable'] = ['Determine parent table', 'Determine the parent entity automatically if possible, or set it manually.'];
$lang['elm_whichPtable_options'] = [
    'auto' => 'Determine parent table automatically',
    'dynamic' => 'Use column with dynamic parent table',
    'static' => 'Set a static parent table',
];
$lang['elm_fieldPtable'] = ['Parent table field', 'Please select the field containing the table name of the parent entity.'];
$lang['elm_tablePtable'] = ['Parent table', 'Please select the table representing the parent entity.'];
$lang['elm_fieldGeneric'] = ['Field', 'Please select the field used by the filter.'];
$lang['elm_columnsGeneric'] = ['Columns', 'Please select the columns that the filter should use.'];
###< filter_legend ###

###> date_legend ###
$lang['date_legend'] = 'Date Settings';
$lang['date_start_legend'] = 'Start Date Settings';
$lang['date_stop_legend'] = 'End Date Settings';
$lang['elm_configureStart'] = ['Configure start date', 'Please choose the configuration for the start date.'];
$lang['elm_startAt'] = ['Start date', 'Please enter a start date.'];
$lang['elm_configureStop'] = ['Configure end date', 'Please choose the configuration for the end date.'];
$lang['elm_stopAt'] = ['End date', 'Please enter an end date.'];
$lang['elm_hasExtendedEvents'] = ['Extend events', 'Show all events within the time range, even if they start earlier and overlap the range.'];
###< date_legend ###

###> date_limits_legend ###
$lang['date_limits_legend'] = 'Date limits';
$lang['elm_isLimited'] = ['Limit the date selection', 'Limit the date selection in the form to the configured start and stop date.'];
###< date_limits_legend ###

###> archive_legend ###
$lang['archive_legend'] = 'Archive Settings';
$lang['elm_whitelistParents'] = ['Parent whitelist', 'Please choose the archives that are allowed for this filter.'];
$lang['elm_groupWhitelistParents'] = ['Define allowed parent archives', 'Please choose the tables and archives allowed for this filter.'];
$lang['elm_groupWhitelistParents_'] = [
    'tablePtable' => ['Parent table', 'Please select the table representing the parent entity.'],
    'whitelistParents' => ['Parent whitelist', 'Please choose the archives that are allowed for this filter.'],
    'formatLabel' => ['Formatting', 'Please select a formatting option for display.'],
    'formatLabel_custom' => 'Custom formatting',
    'formatLabelCustom' => ['Custom formatting', 'Please enter a formatting rule for display.'],
];
$lang['elm_useWhitelistForOptionsOnly'] = ['Apply whitelist to form options only', 'Active: the whitelist only restricts the archives shown in the filter. Without a selection in the form, items from other archives are also displayed.'];
###< archive_legend ###

###> flare_simple_equation_legend ###
$lang['flare_simple_equation_legend'] = 'Equation Settings';
$lang['elm_equationLeft'] = ['Left operand', 'Please select the left operand of the equation.'];
$lang['elm_equationOperator'] = ['Operator', 'Select the operator of the equation.'];
$lang['elm_equationRight'] = ['Right operand', 'Please select the right operand of the equation.'];
###< flare_simple_equation_legend ###

###> control_legend ###
$lang['control_legend'] = 'Control Settings';
$lang['ctl_label'] = ['Label', 'Please enter a label for the field.'];
$lang['ctl_placeholder'] = ['Placeholder', 'Please enter a placeholder text.'];
$lang['ctl_formatLabel'] = ['Formatting', 'Please select a formatting option for display.'];
$lang['ctl_formatLabel_custom'] = 'Custom formatting';
$lang['ctl_formatLabelCustom'] = ['Custom formatting', 'Please enter a formatting rule for display.'];
$lang['ctl_isMandatory'] = ['Mandatory field', 'The field must be filled out.'];
$lang['ctl_isMultiple'] = ['Multiple selection', 'Allow selecting multiple items.'];
$lang['ctl_isExpanded'] = ['Expanded selection', 'Display all items at once in a list with checkboxes.'];
$lang['ctl_hasEmptyOption'] = ['Add empty option', 'Add an empty option at the beginning of the list.'];
$lang['ctl_formatEmptyOption'] = ['Display empty option as', 'Please select a formatting for the empty option.'];
$lang['ctl_formatEmptyOption_custom'] = 'Custom formatting';
$lang['ctl_formatEmptyOptionCustom'] = ['Empty option formatting', 'Please enter a text or label format for the empty option.'];
$lang['ctl_boolBinaryChoices'] = ['Choices', 'Please select the values the checkbox stands for.'];
###< control_legend ###
