<?php

use Contao\DataContainer;
use Contao\DC_Table;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

$dca = &$GLOBALS['TL_DCA'][ListModel::getTable()];

$dca['config'] = [
    'ctable' => [FilterModel::getTable()],
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
        'children' => [
            'href' => 'table=' . FilterModel::getTable(),
            'icon' => 'edit.svg',
        ],
        'edit' => [
            'href' => 'act=edit',
            'icon' => 'header.svg',
        ],
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
        'eval' => [
            'mandatory' => true,
            'includeBlankOption' => true,
            'submitOnChange' => true,
            'tl_class' => 'w50',
        ],
        'sql' => "varchar(32) NOT NULL default ''",
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
    'test' => [
        'inputType' => 'checkbox',
        'default' => true,
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'eval' => [
            'doNotCopy' => true,
            'tl_class' => 'w50',
            'submitOnChange' => true,
        ],
        'sql' => ['type' => 'boolean', 'default' => false],
    ],
    'another_field' => [
        'inputType' => 'text',
        'default' => 'sadf',
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'eval' => ['doNotCopy' => true, 'tl_class' => 'w50'],
        'sql' => "varchar(32) NOT NULL default ''",
    ],
];

$dca['palettes'] = [
    '__selector__' => ['type'],
    '__mask__' => '{title_legend},title,type;__insert__;{publish_legend},published',
    'default' => '{title_legend},title,type;{publish_legend},published',
];

$dca['subpalettes'] = [];
