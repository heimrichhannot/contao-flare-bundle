<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Template;
use HeimrichHannot\FlareBundle\Engine\Engine;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\Event;

class ListViewRenderEvent extends Event
{
    use ModifiesTemplateTrait;

    public function __construct(
        private readonly ContentModel $contentModel,
        private readonly Engine       $engine,
        private readonly ListModel    $listModel,
        private Template              $template,
    ) {}

    public function getContentModel(): ContentModel
    {
        return $this->contentModel;
    }

    public function getEngine(): Engine
    {
        return $this->engine;
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