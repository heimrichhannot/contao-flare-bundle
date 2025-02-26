<?php

namespace HeimrichHannot\FlareBundle\Contao;

use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

class BackendModule
{
    public const CATEGORY = 'design';
    public const NAME = 'flare';

    public static function getTables(): array
    {
        return [
            ListModel::getTable(),
            FilterModel::getTable(),
        ];
    }
}