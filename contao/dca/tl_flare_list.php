<?php

use Contao\DataContainer;
use Contao\DC_Table;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\SortDescriptor\Order;
use HeimrichHannot\FlareBundle\Util\BeActionsHelper;
use HeimrichHannot\FlareBundle\Util\Str;

$table = ListModel::getTable();
$filterTable = FilterModel::getTable();

$dca = &$GLOBALS['TL_DCA'][$table];

$dca['config'] = [
    'ctable' => [$filterTable],
    'dataContainer' => DC_Table::class,
    'enableVersioning' => true,
    'switchToEdit' => true,
    'markAsCopy' => 'title',
    'sql' => [
        'keys' => [
            'id' => 'primary',
        ],
    ],
];

$dca['list'] = [
    'sorting' => [
        'mode' => DataContainer::MODE_SORTED,
        'fields' => ['title'],
        'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
        'panelLayout' => 'filter;search,limit',
        'headerFields' => ['title'],
    ],
    'label' => [
        'fields' => ['title'],
        'format' => '%s',
    ],
    'global_operations' => [
        'all' => [
            'href' => 'act=select',
            'class' => 'header_edit_all',
            'attributes' => 'onclick="Backend.getScrollOffset()"',
        ],
    ],
    'operations' => [
        ...BeActionsHelper::operation(BeActionsHelper::OP_EDIT),
        ...BeActionsHelper::operation(BeActionsHelper::OP_CHILDREN, $filterTable),
        'copy' => [
            'href' => 'act=copy',
            'icon' => 'copy.svg',
        ],
        'toggle' => [
            'href' => 'act=toggle&amp;field=published',
            'icon' => 'visible.svg',
        ],
        'delete' => [
            'href' => 'act=delete',
            'icon' => 'delete.svg',
            'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? 'Confirm delete') . '\'))return false;Backend.getScrollOffset()"',
        ],
        'show' => [
            'href' => 'act=show',
            'icon' => 'show.svg',
        ],
    ]
];

$dca['fields'] = [
    'id' => [
        'sql' => "int(10) unsigned NOT NULL auto_increment",
    ],
    'tstamp' => [
        'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'title' => [
        'inputType' => 'text',
        'exclude' => true,
        'search' => true,
        'sorting' => true,
        'flag' => 1,
        'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'type' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'reference' => &$GLOBALS['TL_LANG']['FLARE']['list'],
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'submitOnChange' => true,
            'tl_class' => 'w50',
            'chosen' => true,
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'published' => [
        'inputType' => 'checkbox',
        'default' => true,
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'eval' => ['doNotCopy' => true, 'tl_class' => 'w50'],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'dc' => [
        'inputType' => 'select',
        'eval' => [
            'mandatory' => true,
            'chosen' => true,
            'submitOnChange' => true,
            'includeBlankOption' => true,
            'tl_class' => 'w50',
        ],
        'exclude' => true,
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'fieldAutoItem' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => false,
        'eval' => [
            'mandatory' => false,
            'chosen' => true,
            'includeBlankOption' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'hasParent' => [
        'exclude' => true,
        'filter' => true,
        'inputType' => 'checkbox',
        'eval' => [
            'tl_class' => 'w50 cbx',
            'alwaysSave' => true,
            'submitOnChange' => true,
        ],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'fieldPid' => [
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
        'sql' => "varchar(128) NULL default ''",
    ],
    'whichPtable' => [
        'exclude' => true,
        'filter' => false,
        'default' => 'auto',
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'radio',
        'options' => ['auto', 'dynamic', 'static'],
        'reference' => &$GLOBALS['TL_LANG'][$table]['whichPtable_options'],
        'eval' => [
            'submitOnChange' => true,
            'alwaysSave' => true,
            'tl_class' => 'w50 clr'
        ],
        'sql' => "varchar(32) NOT NULL default 'auto'",
    ],
    'fieldPtable' => [
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
        'sql' => "varchar(128) NULL default ''",
    ],
    'tablePtable' => [
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
        'sql' => "varchar(128) NULL default ''",
    ],
    'jumpToReader' => [
        'exclude' => true,
        'inputType' => 'pageTree',
        'foreignKey' => 'tl_page.title',
        'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
        'sql' => "int(10) unsigned NOT NULL default 0",
        'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
    ],
    'sortSettings' => [
        'inputType' => 'group',
        'palette' => ['column', 'direction'],
        'fields' => [
            'column' => [
                'label' => &$GLOBALS['TL_LANG'][$table]['sortSettings__column'],
                'inputType' => 'select',
                'exclude' => true,
                'filter' => false,
                'options_callback' => [ListContainer::class, 'getFieldOptions_columns'],
                'eval' => [
                    'mandatory' => true,
                    'chosen' => true,
                    'includeBlankOption' => true,
                    'tl_class' => 'w50',
                ],
            ],
            'direction' => [
                'label' => &$GLOBALS['TL_LANG'][$table]['sortSettings__direction'],
                'inputType' => 'select',
                'exclude' => true,
                'filter' => false,
                'default' => Order::ASC,
                'options' => [Order::ASC, Order::DESC],
                'reference' => &$GLOBALS['TL_LANG']['FLARE']['sort_order'],
                'eval' => [
                    'mandatory' => true,
                    'includeBlankOption' => false,
                    'chosen' => true,
                    'tl_class' => 'w50',
                ],
            ],
        ],
        'order' => true,
        'sql' => ['type' => 'blob', 'notnull' => false]
    ],
    'metaTitleFormat' => [
        'inputType' => 'text',
        'exclude' => true,
        'filter' => false,
        'eval' => ['maxlength' => 255, 'tl_class' => 'w50', 'placeholder' => '##title##'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'metaDescriptionFormat' => [
        'inputType' => 'text',
        'exclude' => true,
        'filter' => false,
        'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'metaRobotsFormat' => [
        'inputType' => 'text',
        'exclude' => true,
        'filter' => false,
        'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
];

###> contao/comments-bundle support
$dca['fields']['comments_enabled'] = [
    'exclude' => true,
    'default' => false,
    'inputType' => 'checkbox',
    'eval' => ['doNotCopy' => true, 'tl_class' => 'clr w50', 'submitOnChange' => true],
    'sql' => ['type' => 'boolean', 'default' => false],
];
$dca['fields']['comments_sendNativeEmails'] = [
    'exclude' => true,
    'default' => true,
    'inputType' => 'checkbox',
    'eval' => ['doNotCopy' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'boolean', 'default' => true],
];
###< contao/comments-bundle support

$dca['palettes'] = [
    '__selector__' => ['type', 'whichPtable'],
    '__prefix__' => '{title_legend},title,type',
    '__suffix__' => '{flare_defaults_legend},sortSettings;{flare_reader_legend},jumpToReader;{publish_legend},published',
];

$dca['palettes']['default'] = Str::mergePalettes($dca['palettes']['__prefix__'], $dca['palettes']['__suffix__']);

$dca['subpalettes'] = [
    'whichPtable_auto' => '',
    'whichPtable_dynamic' => 'fieldPtable',
    'whichPtable_static' => 'tablePtable',
];
