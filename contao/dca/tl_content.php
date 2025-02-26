<?php

use HeimrichHannot\FlareBundle\Controller\ContentElement\ViewController;
use HeimrichHannot\FlareBundle\Model\ListModel;

$dca = &$GLOBALS['TL_DCA']['tl_content'];

$dca['fields'][ViewController::TYPE] = [
    'exclude' => true,
    'inputType' => 'select',
    'foreignKey' => \sprintf('%s.title', ListModel::getTable()),
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
    'eval' => [
        'mandatory' => true,
        'chosen' => true,
        'includeBlankOption' => true,
        'tl_class' => 'w50',
    ],
    'sql' => "int(10) unsigned NOT NULL default 0",
];

$dca['palettes'][ViewController::TYPE] = '{type_legend},type,headline;'
    . '{flare_list_legend},flare_list;'  # translate these keys
    . '{template_legend:hide},customTpl;'
    . '{protected_legend:hide},protected;'
    . '{expert_legend:hide},guests,cssID;'
    . '{invisible_legend:hide},invisible,start,stop';
