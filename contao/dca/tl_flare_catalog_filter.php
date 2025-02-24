<?php

use Contao\DC_Table;
use HeimrichHannot\FlareBundle\DataContainer\CatalogFilterContainer;

$dca = &$GLOBALS['TL_DCA'][CatalogFilterContainer::TABLE_NAME];

$dca['config'] = [
    'dataContainer' => DC_Table::class,
    'enableVersioning' => true,
    'ptable' => 'tl_flare_catalog',
    'switchToEdit' => true,
    'sql' => [
        'keys' => [
            'id' => 'primary',
        ],
    ],
];

$dca['list'] = [
    'sorting' => [
        'mode' => 4,
        'flag' => 11,
        'panelLayout' => 'filter,sort;search,limit',
        'fields' => ['title'],
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
            'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
        ],
    ],
    'operations' => [
        'edit' => [
            'href' => 'act=edit',
            'icon' => 'edit.svg',
        ],
        'copy' => [
            'href' => 'act=copy',
            'icon' => 'copy.svg',
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
        'toggle' => [
            'href' => 'act=toggle&amp;field=published',
            'icon' => 'visible.svg',
        ],
    ],
];

$dca['fields'] = [
    'id' => [
        'sql' => "int(10) unsigned NOT NULL auto_increment",
    ],
    'pid' => [
        'foreignKey' => 'tl_flare_catalog.title',
        'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'tstamp' => [
        'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'title' => [
        'inputType' => 'text',
        'exclude' => true,
        'search' => true,
        'sorting' => true,
        'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'type' => [
        'inputType' => 'select',
        'exclude' => true,
        'filter' => true,
        'sorting' => true,
        'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
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
];

$dca['palettes'] = [
    '__selector__' => ['published'],
    'default' => '{title_legend},title,type;{publish_legend},published;',
];
