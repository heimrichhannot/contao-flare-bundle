<?php

use HeimrichHannot\FlareBundle\Contao\BackendModule;

$mod = &$GLOBALS['BE_MOD']['content'][BackendModule::NAME];

$mod = [
    'tables' => ['tl_flare_catalog', 'tl_flare_catalog_filter'],
];
