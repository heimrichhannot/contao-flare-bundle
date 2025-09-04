<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use Contao\Model;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class ReaderPageSchemaOrgConfig
{
    public function __construct(
        public ListModel $listModel,
        public Model     $model,
    ) {}
}