<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class ListItemProviderConfig
{
    public function __construct(
        private ListModel $listModel,
    ) {}

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }
}