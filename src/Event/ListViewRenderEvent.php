<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Template;
use HeimrichHannot\FlareBundle\ListView\ListView;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class ListViewRenderEvent extends AbstractTemplateRenderEvent
{
    public function __construct(
        private readonly ContentModel      $contentModel,
        private readonly ListSpecification $listSpecification,
        private readonly ListModel         $listModel,
        private readonly ListView          $listView,
        private Template                   $template,
    ) {}

    public function getContentModel(): ContentModel
    {
        return $this->contentModel;
    }

    public function getListSpecification(): ListSpecification
    {
        return $this->listSpecification;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getListView(): ListView
    {
        return $this->listView;
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