<?php

use Contao\DataContainer;
use Contao\DC_Table;
use HeimrichHannot\FlareBundle\Enum\BoolBinaryChoices;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\EventListener\Contao\FormNameRegexpListener;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use HeimrichHannot\FlareBundle\Util\BeActionsHelper;
use HeimrichHannot\FlareBundle\Util\Str;

$dca = &$GLOBALS['TL_DCA'][$table = FilterModel::getTable()];

$dca['config'] = [
    'dataContainer' => DC_Table::class,
    'enableVersioning' => true,
    'ptable' => ListModel::getTable(),
    'switchToEdit' => true,
    'sql' => [
        'keys' => [
            'id' => 'primary',
            'pid,published' => 'index',
            'alias' => 'index',
            'pid,alias' => 'index',
        ],
    ],
];

$dca['list'] = [
    'sorting' => [
        'mode' => DataContainer::MODE_PARENT,
        'flag' => 11,
        'panelLayout' => 'filter;search,limit',
        'fields' => ['sorting'],
        'headerFields' => ['title', 'type', 'dc'],
    ],
    'label' => [
        'fields' => ['title'],
        'format' => '%s',
    ],
    'global_operations' => [
        'all' => [
            'href' => 'act=select',
            'class' => 'header_edit_all',
            'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
        ],
    ],
    'operations' => [
        ...BeActionsHelper::operation(BeActionsHelper::OP_EDIT),
        'copy' => [
            'href' => 'act=copy&amp;mode=copy',
            'icon' => 'copy.svg',
        ],
        'cut' => [
            'href' => 'act=paste&amp;mode=cut',
            'icon' => 'cut.svg',
        ],
        'delete' => [
            'href' => 'act=delete',
            'icon' => 'delete.svg',
            'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? 'Confirm delete') . '\'))return false;Backend.getScrollOffset()"',
        ],
        'toggle' => [
            'href' => 'act=toggle&amp;field=published',
            'icon' => 'visible.svg',
            'primary' => true,
        ],
        'show' => [
            'href' => 'act=show',
            'icon' => 'show.svg',
        ],
    ],
];

$dca['fields'] = [
    'id' => [
        'sql' => "int(10) unsigned NOT NULL auto_increment",
    ],
    'pid' => [
        'foreignKey' => \sprintf('%s.title', ListModel::getTable()),
        'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'sorting' => [
        'flag' => DataContainer::SORT_ASC,
        'sql' => "int(10) unsigned NOT NULL default 0",
    ],
    'tstamp' => [
        'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'title' => [
        'exclude' => true,
        'search' => true,
        'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
        'inputType' => 'text',
        'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'type' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => true,
        'search' => true,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'submitOnChange' => true,
            'tl_class' => 'w50',
            'chosen' => true,
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'control' => [
        'exclude' => true,
        'inputType' => 'select',
        'eval' => [
            'mandatory' => false,
            'includeBlankOption' => true,
            'submitOnChange' => true,
            'alwaysSave' => true,
            'chosen' => true,
            'tl_class' => 'w50',
        ],
        'sql' => ['type' => 'string', 'length' => 128, 'default' => '', 'notnull' => true],
    ],
    'value' => [
        'exclude' => true,
        'eval' => ['tl_class' => 'clr'],
        'sql' => ['type' => 'blob', 'notnull' => false],
    ],
    'targetAlias' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => true,
        'default' => TableAliasRegistry::ALIAS_MAIN,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => false,
            'alwaysSave' => true,
            'chosen' => true,
            'tl_class' => 'w50',
            'submitOnChange' => true,
        ],
        'sql' => ['type' => 'string', 'length' => 128, 'default' => TableAliasRegistry::ALIAS_MAIN],
    ],
    'alias' => [
        'exclude' => true,
        'search' => true,
        'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
        'inputType' => 'text',
        'eval' => [
            'mandatory' => false,
            'maxlength' => 128,
            'tl_class' => 'w50',
            'doNotCopy' => true,
            'rgxp' => FormNameRegexpListener::RGXP,
        ],
        'sql' => ['type' => 'string', 'length' => 128, 'default' => '', 'notnull' => true],
    ],
    'published' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => true,
        'default' => true,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => ['doNotCopy' => true, 'tl_class' => 'w50'],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'elm_usePublished' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => false,
        'default' => true,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => true,
            'tl_class' => 'cbx w50 clr'
        ],
        'sql' => ['type' => 'boolean', 'default' => true],
    ],
    'elm_fieldPublished' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => false,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'chosen' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'elm_invertPublished' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => false,
        'default' => false,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'tl_class' => 'w50'
        ],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'elm_useStart' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => false,
        'default' => true,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => true,
            'tl_class' => 'cbx w50 clr'
        ],
        'sql' => ['type' => 'boolean', 'default' => true],
    ],
    'elm_fieldStart' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => false,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'chosen' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'elm_useStop' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => false,
        'default' => true,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => true,
            'tl_class' => 'cbx w50 clr'
        ],
        'sql' => ['type' => 'boolean', 'default' => true],
    ],
    'elm_fieldStop' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => false,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'chosen' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'elm_fieldPid' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => false,
        'eval' => [
            'submitOnChange' => true,
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'chosen' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'elm_whichPtable' => [
        'exclude' => true,
        'filter' => false,
        'default' => 'auto',
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'radio',
        'options' => ['auto', 'dynamic', 'static'],
        'reference' => &$GLOBALS['TL_LANG'][$table]['elm_whichPtable_options'],
        'eval' => [
            'submitOnChange' => true,
            'alwaysSave' => true,
            'tl_class' => 'w50 clr'
        ],
        'sql' => "varchar(32) NOT NULL default 'auto'",
    ],
    'elm_fieldPtable' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => false,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'chosen' => true,
            'tl_class' => 'w50 clr',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'elm_tablePtable' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => false,
        'eval' => [
            'mandatory' => true,
            'submitOnChange' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'chosen' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'elm_fieldGeneric' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => false,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'chosen' => true,
            'tl_class' => 'w50 clr',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'elm_columnsGeneric' => [ // "columns" instead of "fields" to prevent confusion and typos
        'inputType' => 'select',
        'exclude' => true,
        'filter' => false,
        'default' => null,
        'eval' => [
            'chosen' => true,
            'multiple' => true,
            'mandatory' => true,
            'includeBlankOption' => true,
            'tl_class' => 'clr w100',
        ],
        'sql' => ['type' => 'blob', 'notnull' => false],
    ],
    'elm_configureStart' => [
        'exclude' => true,
        'search' => false,
        'inputType' => 'select',
        'reference' => &$GLOBALS['TL_LANG']['FLARE']['date_time'],
        'default' => 'today',
        'eval' => [
            'mandatory' => false,
            'maxlength' => 255,
            'tl_class' => 'clr w50',
            'includeBlankOption' => true,
            'chosen' => true,
            'submitOnChange' => true,
            'alwaysSave' => true,
        ],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'elm_startAt' => [
        'exclude' => true,
        'search' => false,
        'inputType' => 'text',
        'eval' => [
            'mandatory' => false,
            'maxlength' => 255,
            'rgxp' => 'datim',
            'datepicker' => true,
            'tl_class' => 'w50 wizard',
        ],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'elm_configureStop' => [
        'exclude' => true,
        'search' => false,
        'inputType' => 'select',
        'reference' => &$GLOBALS['TL_LANG']['FLARE']['date_time'],
        'default' => '',
        'eval' => [
            'mandatory' => false,
            'maxlength' => 255,
            'tl_class' => 'clr w50',
            'includeBlankOption' => true,
            'chosen' => true,
            'submitOnChange' => true,
            'alwaysSave' => true,
        ],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'elm_stopAt' => [
        'exclude' => true,
        'search' => false,
        'inputType' => 'text',
        'eval' => [
            'mandatory' => false,
            'maxlength' => 255,
            'rgxp' => 'datim',
            'datepicker' => true,
            'tl_class' => 'w50 wizard',
        ],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'elm_hasExtendedEvents' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => false,
        'default' => false,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'tl_class' => 'cbx w50 clr'
        ],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'elm_isLimited' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => false,
        'default' => false,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => false,
            'tl_class' => 'cbx w50'
        ],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'elm_whitelistParents' => [
        'inputType' => 'select',
        'default' => null,
        'eval' => [
            'chosen' => true,
            'multiple' => true,
        ],
        'sql' => ['type' => 'blob', 'notnull' => false],
    ],
    'elm_groupWhitelistParents' => [
        'inputType' => 'group',
        // The sub-fields are the keys of the stored blob, not columns: they keep their unprefixed names and are
        // defined here rather than referencing the (renamed) columns.
        'palette' => ['tablePtable', 'whitelistParents', 'formatLabel', 'formatLabelCustom'],
        'fields' => [
            'tablePtable' => [
                'inputType' => 'select',
                'eval' => [
                    'mandatory' => true,
                    'submitOnChange' => true,
                    'includeBlankOption' => true,
                    'alwaysSave' => true,
                    'chosen' => true,
                    'tl_class' => 'clr w50',
                ],
            ],
            'whitelistParents' => [
                'inputType' => 'select',
                'eval' => ['chosen' => true, 'multiple' => true, 'tl_class' => 'w50'],
            ],
            'formatLabel' => [
                'inputType' => 'select',
                'eval' => [
                    'mandatory' => false,
                    'submitOnChange' => true,
                    'includeBlankOption' => true,
                    'alwaysSave' => true,
                    'chosen' => true,
                    'tl_class' => 'w50',
                ],
            ],
            'formatLabelCustom' => [
                'inputType' => 'text',
                'eval' => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            ],
        ],
        'order' => false,
        'sql' => ['type' => 'blob', 'notnull' => false]
    ],
    'elm_useWhitelistForOptionsOnly' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => false,
        'default' => false,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => false,
            'tl_class' => 'cbx w50 m12'
        ],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'elm_equationLeft' => [
        'exclude' => true,
        'search' => false,
        'inputType' => 'select',
        'eval' => ['mandatory' => true, 'maxlength' => 255, 'chosen' => true],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'elm_equationOperator' => [
        'exclude' => true,
        'search' => false,
        'inputType' => 'select',
        'options' => SqlEquationOperator::asOptions(false),
        'eval' => [
            'mandatory' => true,
            'maxlength' => 255,
            'alwaysSave' => true,
            'submitOnChange' => true,
            'chosen' => true,
        ],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'elm_equationRight' => [
        'exclude' => true,
        'search' => false,
        'inputType' => 'text',
        'eval' => ['mandatory' => false, 'maxlength' => 255, 'chosen' => true],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'ctl_label' => [
        'exclude' => true,
        'search' => false,
        'inputType' => 'text',
        'eval' => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'ctl_placeholder' => [
        'exclude' => true,
        'search' => false,
        'inputType' => 'text',
        'eval' => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'ctl_formatLabel' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => false,
        'eval' => [
            'mandatory' => false,
            'submitOnChange' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'chosen' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'ctl_formatLabelCustom' => [
        'exclude' => true,
        'search' => false,
        'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
        'inputType' => 'text',
        'eval' => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'ctl_isMandatory' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => false,
        'default' => false,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => false,
            'tl_class' => 'cbx w50'
        ],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'ctl_isMultiple' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => false,
        'default' => false,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => true,
            'tl_class' => 'cbx w50'
        ],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'ctl_isExpanded' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => false,
        'default' => false,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => false,
            'tl_class' => 'cbx w50'
        ],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'ctl_hasEmptyOption' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => false,
        'default' => false,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => true,
            'tl_class' => 'cbx w50'
        ],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'ctl_formatEmptyOption' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => false,
        'eval' => [
            'mandatory' => false,
            'submitOnChange' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'chosen' => true,
            'tl_class' => 'w50 clr',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'ctl_formatEmptyOptionCustom' => [
        'exclude' => true,
        'search' => false,
        'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
        'inputType' => 'text',
        'eval' => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'ctl_boolBinaryChoices' => [
        'exclude' => true,
        'search' => false,
        'inputType' => 'select',
        'options' => BoolBinaryChoices::asOptions(),
        'eval' => ['mandatory' => true, 'maxlength' => 255, 'alwaysSave' => true, 'tl_class' => 'w50 clr'],
        'sql' => "varchar(64) NOT NULL default ''",
    ],
];

$dca['palettes'] = [
    '__selector__' => [
        'type', 'elm_usePublished', 'elm_useStart', 'elm_useStop', 'elm_whichPtable', 'elm_configureStart',
        'elm_configureStop', 'ctl_formatLabel', 'ctl_formatEmptyOption',
    ],
    '__prefix__' => '{title_legend},title,type,control',
    '__suffix__' => '{expert_legend:hide},alias;{publish_legend},published',
];

$dca['palettes']['default'] = Str::mergePalettes($dca['palettes']['__prefix__'], $dca['palettes']['__suffix__']);

$dca['subpalettes'] = [
    'elm_usePublished' => 'elm_fieldPublished,elm_invertPublished',
    'elm_useStart' => 'elm_fieldStart',
    'elm_useStop' => 'elm_fieldStop',
    'elm_whichPtable_auto' => '',
    'elm_whichPtable_dynamic' => 'elm_fieldPtable',
    'elm_whichPtable_static' => 'elm_tablePtable',
    'elm_configureStart_str' => 'elm_startAt',
    'elm_configureStart_date' => 'elm_startAt',
    'elm_configureStop_str' => 'elm_stopAt',
    'elm_configureStop_date' => 'elm_stopAt',
    'ctl_formatLabel_custom' => 'ctl_formatLabelCustom',
    'ctl_formatEmptyOption_custom' => 'ctl_formatEmptyOptionCustom',
];
