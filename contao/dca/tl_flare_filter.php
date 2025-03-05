<?php

use Contao\DataContainer;
use Contao\DC_Table;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\Str;

$dca = &$GLOBALS['TL_DCA'][FilterModel::getTable()];

$dca['config'] = [
    'dataContainer' => DC_Table::class,
    'enableVersioning' => true,
    'ptable' => ListModel::getTable(),
    'switchToEdit' => true,
    'sql' => [
        'keys' => [
            'id' => 'primary',
            'pid,published' => 'index'
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
        'edit' => [
            'href' => 'act=edit',
            'icon' => 'edit.svg',
        ],
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
        'sorting' => true,
        'flag' => DataContainer::SORT_ASC,
        'sql' => "int(10) unsigned NOT NULL default 0",
    ],
    'tstamp' => [
        'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'title' => [
        'exclude' => true,
        'search' => true,
        'sorting' => true,
        'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
        'inputType' => 'text',
        'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'type' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'submitOnChange' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(32) NOT NULL default ''",
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
    'intrinsic' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => true,
        'default' => true,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'cbx w50'],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'usePublished' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => true,
        'default' => true,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => true,
            'tl_class' => 'cbx w50 clr'
        ],
        'sql' => ['type' => 'boolean', 'default' => true],
    ],
    'useStart' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => true,
        'default' => true,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => true,
            'tl_class' => 'cbx w50 clr'
        ],
        'sql' => ['type' => 'boolean', 'default' => true],
    ],
    'useStop' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => true,
        'default' => true,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'submitOnChange' => true,
            'tl_class' => 'cbx w50 clr'
        ],
        'sql' => ['type' => 'boolean', 'default' => true],
    ],
    'whichPtable' => [
        'exclude' => true,
        'filter' => true,
        'default' => 'auto',
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'radio',
        'options' => ['auto', 'dynamic', 'static'],
        'reference' => &$GLOBALS['TL_LANG'][FilterModel::getTable()]['whichPtable_options'],
        'eval' => [
            'submitOnChange' => true,
            'alwaysSave' => true,
            'tl_class' => 'w50 clr'
        ],
        'sql' => "varchar(32) NOT NULL default 'auto'",
    ],
    'invertPublished' => [
        'exclude' => true,
        'toggle' => true,
        'filter' => true,
        'default' => false,
        'flag' => DataContainer::SORT_INITIAL_LETTER_DESC,
        'inputType' => 'checkbox',
        'eval' => [
            'tl_class' => 'w50'
        ],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'fieldPublished' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'fieldStart' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'fieldStop' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'fieldPid' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'fieldPtable' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'tl_class' => 'w50 clr',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'tablePtable' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'alwaysSave' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(128) NOT NULL default ''",
    ]
];

$dca['palettes'] = [
    '__selector__' => ['type', 'usePublished', 'useStart', 'useStop', 'whichPtable'],
    '__prefix__' => '{title_legend},title,type,intrinsic',
    '__suffix__' => '{publish_legend},published',
];

$dca['palettes']['default'] = Str::mergePalettes($dca['palettes']['__prefix__'], $dca['palettes']['__suffix__']);

$dca['subpalettes'] = [
    'usePublished' => 'fieldPublished,invertPublished',
    'useStart' => 'fieldStart',
    'useStop' => 'fieldStop',
    'whichPtable_auto' => '',
    'whichPtable_dynamic' => 'fieldPtable',
    'whichPtable_static' => 'tablePtable',
];
