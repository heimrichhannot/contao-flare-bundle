<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Template;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\ListView\ListView;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;

class ListViewRenderEvent extends AbstractTemplateRenderEvent
{
    public function __construct(
        private readonly ContentContext  $contentContext,
        private readonly ContentModel    $contentModel,
        private readonly ListModel       $listModel,
        private readonly ListView        $listView,
        private readonly PaginatorConfig $paginatorConfig,
        private Template                 $template,
    ) {}

    public function getContentContext(): ContentContext
    {
        return $this->contentContext;
    }

    public function getContentModel(): ContentModel
    {
        return $this->contentModel;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getListView(): ListView
    {
        return $this->listView;
    }

    public function getPaginatorConfig(): PaginatorConfig
    {
        return $this->paginatorConfig;
    }

    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function setTemplate(Template $template): void
    {
        $this->template = $template;
    }
}