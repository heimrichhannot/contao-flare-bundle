<?php

use HeimrichHannot\FlareBundle\Model\FilterModel;

$lang = &$GLOBALS['TL_LANG'][FilterModel::getTable()];

###> title_legend ###
$lang['title_legend'] = 'General';
$lang['title'] = ['Title', 'Please enter a title for this list filter.'];
$lang['type'] = ['Type', 'Please select the type of the filter.'];
$lang['intrinsic'] = ['Intrinsic', 'Always apply this filter and hide it from the user.'];
$lang['targetAlias'] = ['Apply to', 'Select the relation this filter should be applied to.'];
###< title_legend ###

###> publish_legend ###
$lang['publish_legend'] = 'Publishing';
$lang['published'] = ['Published', 'Whether the filter is active.'];
###< publish_legend ###

###> filter_legend ###
$lang['filter_legend'] = 'Filter Settings';
$lang['usePublished'] = ['Consider published state', 'Consider a field representing the published state.'];
$lang['useStart'] = ['Consider start date', 'Consider the element’s start date.'];
$lang['useStop'] = ['Consider end date', 'Consider the element’s end date.'];
$lang['useTablePtable'] = ['Set static archive table', 'Ignore other settings and define a static archive table.'];
$lang['invertPublished'] = ['Invert published state', 'Enable this if the field marks entries as hidden instead of visible.'];
$lang['fieldPublished'] = ['Published field', 'Please select the field representing the published state.'];
$lang['fieldStart'] = ['Start date field', 'Please select the field that should be used as the start date.'];
$lang['fieldStop'] = ['End date field', 'Please select the field that should be used as the end date.'];
$lang['fieldPid'] = ['Parent ID field', 'Please select the field containing the ID of the parent entity.'];
$lang['fieldPtable'] = ['Parent table field', 'Please select the field containing the table name of the parent entity.'];
$lang['fieldGeneric'] = ['Field', 'Please select the field used by the filter.'];
$lang['columnsGeneric'] = ['Columns', 'Please select the columns that the filter should use.'];
$lang['tablePtable'] = ['Parent table', 'Please select the table representing the parent entity.'];
$lang['whichPtable'] = ['Determine parent table', 'Determine the parent entity automatically if possible, or set it manually.'];
$lang['whichPtable_options'] = [
    'auto' => 'Determine parent table automatically',
    'dynamic' => 'Use column with dynamic parent table',
    'static' => 'Set a static parent table',
];
###< filter_legend ###

###> date_legend ###
$lang['date_legend'] = 'Date Settings';
$lang['date_start_legend'] = 'Start Date Settings';
$lang['date_stop_legend'] = 'End Date Settings';
$lang['configureStart'] = ['Configure start date', 'Please choose the configuration for the start date.'];
$lang['configureStop'] = ['Configure end date', 'Please choose the configuration for the end date.'];
$lang['startAt'] = ['Start date', 'Please enter a start date.'];
$lang['stopAt'] = ['End date', 'Please enter an end date.'];
$lang['hasExtendedEvents'] = ['Extend events', 'Show all events within the time range, even if they start earlier and overlap the range.'];
###< date_legend ###

###> archive_legend ###
$lang['archive_legend'] = 'Archive Settings';
$lang['whitelistParents'] = ['Parent whitelist', 'Please choose the archives that are allowed for this filter.'];
$lang['groupWhitelistParents'] = ['Define allowed parent archives', 'Please choose the tables and archives allowed for this filter.'];
$lang['formatLabel'] = ['Formatting', 'Please select a formatting option for display.'];
$lang['formatLabel_custom'] = 'Custom formatting';
$lang['formatLabelCustom'] = ['Custom formatting', 'Please enter a formatting rule for display.'];
###< archive_legend ###

###> form_legend ###
$lang['form_legend'] = 'Form Settings';
$lang['isMandatory'] = ['Mandatory field', 'The field must be filled out.'];
$lang['isMultiple'] = ['Multiple selection', 'Allow selecting multiple items.'];
$lang['isExpanded'] = ['Expanded selection', 'Display all items at once in a list with checkboxes.'];
$lang['isLimited'] = ['Restricted selection', 'Restrict available choices by predefined options.'];
$lang['hasEmptyOption'] = ['Add empty option', 'Add an empty option at the beginning of the list.'];
$lang['formatEmptyOption'] = ['Display empty option as', 'Please select a formatting for the empty option.'];
$lang['formatEmptyOption_custom'] = 'Custom formatting';
$lang['formatEmptyOptionCustom'] = ['Empty option formatting', 'Please enter a text or label format for the empty option.'];
$lang['preselect'] = ['Preselection', 'Specify which values should be preselected.'];
$lang['placeholder'] = ['Placeholder', 'Please enter a placeholder text.'];
$lang['label'] = ['Label', 'Please enter a label for the field.'];
###< form_legend ###

###> flare_simple_equation_legend ###
$lang['flare_simple_equation_legend'] = 'Equation Settings';
$lang['equationOperator'] = ['Operator', 'Select the operator of the equation.'];
$lang['equationLeft'] = ['Left operand', 'Please select the left operand of the equation.'];
$lang['equationRight'] = ['Right operand', 'Please select the right operand of the equation.'];
###< flare_simple_equation_legend ###

###> expert_legend ###
$lang['expert_legend'] = 'Expert Settings';
$lang['formAlias'] = ['Form field alias', 'Enter an alias for this filter’s form field.'];
###< expert_legend ###
