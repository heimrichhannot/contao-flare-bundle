<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use Contao\ContentModel;
use Contao\Model;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Model\ListModel;

readonly class ReaderPageMetaConfig
{
    public function __construct(
        private ListModel      $listModel,
        private Model          $model,
        private ContentContext $contentContext,
        private ContentModel   $contentModel,
    ) {}

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getContentContext(): ContentContext
    {
        return $this->contentContext;
    }

    public function getContentModel(): ContentModel
    {
        return $this->contentModel;
    }
}