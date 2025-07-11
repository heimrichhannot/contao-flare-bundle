<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Filter\FilterElementConfig;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

class InScopeConfig
{
    public function __construct(
        private readonly ContentContext $contentContext,
        private readonly ListModel $listModel,
        private readonly FilterModel $filterModel,
        private readonly FilterElementConfig $filterElementConfig,
    ) {}

    public function getContentContext(): ContentContext
    {
        return $this->contentContext;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getFilterModel(): FilterModel
    {
        return $this->filterModel;
    }

    public function getFilterElementConfig(): FilterElementConfig
    {
        return $this->filterElementConfig;
    }
}