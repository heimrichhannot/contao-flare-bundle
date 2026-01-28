<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Template;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\View\InteractiveView;

class ListViewRenderEvent extends AbstractTemplateRenderEvent
{
    public function __construct(
        private readonly ContentModel      $contentModel,
        private readonly InteractiveView   $interactiveView,
        private readonly ListSpecification $listSpecification,
        private readonly ListModel         $listModel,
        private Template                   $template,
    ) {}

    public function getContentModel(): ContentModel
    {
        return $this->contentModel;
    }

    public function getInteractiveView(): InteractiveView
    {
        return $this->interactiveView;
    }

    public function getListSpecification(): ListSpecification
    {
        return $this->listSpecification;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
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