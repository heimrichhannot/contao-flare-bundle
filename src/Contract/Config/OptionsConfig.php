<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class OptionsConfig
{
    public function __construct(
        private DataContainer $dataContainer,
        private FilterModel   $filterModel,
        private ListModel     $listModel,
    ) {}

    public function getDataContainer(): DataContainer
    {
        return $this->dataContainer;
    }

    public function getFilterModel(): FilterModel
    {
        return $this->filterModel;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }
}