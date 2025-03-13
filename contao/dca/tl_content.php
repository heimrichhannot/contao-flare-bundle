<?php

use HeimrichHannot\FlareBundle\Controller\ContentElement\ListViewController;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Model\ListModel;

$dca = &$GLOBALS['TL_DCA']['tl_content'];

$dca['fields'][$list = ContentContainer::FIELD_LIST] = [
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

$dca['fields'][$formName = ContentContainer::FIELD_FORM_NAME] = [
    'inputType' => 'text',
    'exclude' => true,
    'search' => true,
    'sorting' => true,
    'flag' => 1,
    'eval' => ['mandatory' => false, 'maxlength' => 64, 'tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];

$tplField = [
    'exclude' => true,
    'inputType' => 'select',
    'eval' => ['chosen' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(64) COLLATE ascii_bin NOT NULL default ''",
];

/*$dca['fields'][$errTpl = ContentContainer::FIELD_ERROR_TEMPLATE] = $tplField;
$dca['fields'][$formTpl = ContentContainer::FIELD_FORM_TEMPLATE] = $tplField;
$dca['fields'][$listTpl = ContentContainer::FIELD_LIST_TEMPLATE] = $tplField;*/

$dca['palettes'][ListViewController::TYPE] = '{type_legend},type,headline;'
    . "{flare_list_legend},$list,$formName;"
    . "{template_legend:hide},customTpl;" // ,$errTpl,$formTpl,$listTpl
    . '{protected_legend:hide},protected;'
    . '{expert_legend:hide},guests,cssID;'
    . '{invisible_legend:hide},invisible,start,stop';
