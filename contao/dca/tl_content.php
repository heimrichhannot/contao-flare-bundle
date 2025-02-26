<?php

use HeimrichHannot\FlareBundle\Controller\ContentElement\ListViewController;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Model\ListModel;

$dca = &$GLOBALS['TL_DCA']['tl_content'];

$dca['fields'][ContentContainer::FIELD_LIST] = [
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

$dca['palettes'][ListViewController::TYPE] = '{type_legend},type,headline;'
    . \sprintf('{flare_list_legend},%s;', ContentContainer::FIELD_LIST)  # translate these keys
    . '{template_legend:hide},customTpl;'
    . '{protected_legend:hide},protected;'
    . '{expert_legend:hide},guests,cssID;'
    . '{invisible_legend:hide},invisible,start,stop';
