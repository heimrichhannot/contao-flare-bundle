<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

$dca = &$GLOBALS['TL_DCA']['tl_content'];

$dca['fields']['flare_catalog'] = [
    'exclude' => true,
    'inputType' => 'select',
    'foreignKey' => 'tl_flare_catalog.title',
    'eval' => [
        'mandatory' => true,
        'chosen' => true,
        'includeBlankOption' => true,
        'tl_class' => 'w50',
    ],
    'sql' => "int(10) unsigned NOT NULL default 0",
];

$dca['palettes']['flare_catalog'] = '{type_legend},type,headline;'
    . '{flare_catalog_legend},flare_catalog;'
    . '{template_legend:hide},customTpl;'
    . '{protected_legend:hide},protected;'
    . '{expert_legend:hide},guests,cssID;'
    . '{invisible_legend:hide},invisible,start,stop';
