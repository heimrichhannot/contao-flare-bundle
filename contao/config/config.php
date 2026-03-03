<?php

use HeimrichHannot\FlareBundle\Contao\BackendModule;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

$GLOBALS['TL_MODELS'][ListModel::getTable()] = ListModel::class;
$GLOBALS['TL_MODELS'][FilterModel::getTable()] = FilterModel::class;

$GLOBALS['BE_MOD'][BackendModule::CATEGORY][BackendModule::NAME] = [
    'tables' => BackendModule::getTables(),
];
