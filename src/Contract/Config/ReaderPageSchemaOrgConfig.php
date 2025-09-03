<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use Contao\Model;
use HeimrichHannot\FlareBundle\Model\ListModel;

class ReaderPageSchemaOrgConfig
{
    public function __construct(
        public readonly ListModel $listModel,
        public readonly Model     $model,
    )
    {
    }
}