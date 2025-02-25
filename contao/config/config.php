<?php

use HeimrichHannot\FlareBundle\Contao\BackendModule;
use HeimrichHannot\FlareBundle\Model\CatalogFilterModel;
use HeimrichHannot\FlareBundle\Model\CatalogModel;

$GLOBALS['TL_MODELS'][CatalogModel::getTable()] = CatalogModel::class;
$GLOBALS['TL_MODELS'][CatalogFilterModel::getTable()] = CatalogFilterModel::class;

$GLOBALS['BE_MOD'][BackendModule::CATEGORY][BackendModule::NAME] = [
    'tables' => BackendModule::TABLES,
];
