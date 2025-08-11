<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class InScopeConfig
{
    public function __construct(
        private ContentContext          $contentContext,
        private ListModel               $listModel,
        private FilterModel             $filterModel,
        private FilterElementDescriptor $descriptor,
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

    public function getDescriptor(): FilterElementDescriptor
    {
        return $this->descriptor;
    }
}