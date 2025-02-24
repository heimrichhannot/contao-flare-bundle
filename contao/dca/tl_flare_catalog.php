<?php

use Contao\DC_Table;
use HeimrichHannot\FlareBundle\DataContainer\CatalogContainer;

$dca = &$GLOBALS['TL_DCA'][CatalogContainer::TABLE_NAME];

$dca['config'] = [
    'ctable' => ['tl_flare_catalog_filter'],
    'dataContainer' => DC_Table::class,
    'enableVersioning' => true,
    'switchToEdit' => true,
    'sql' => [
        'keys' => [
            'id' => 'primary',
        ],
    ],
];

$dca['list'] = [
    'sorting' => [
        'mode' => 2,
        'flag' => 1,
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
            'attributes' => 'onclick="Backend.getScrollOffset()"',
        ],
    ],
    'operations' => [
        'children' => [
            'href' => 'table=tl_flare_catalog_filter',
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
    'default' => '{title_legend},title,published',
];
