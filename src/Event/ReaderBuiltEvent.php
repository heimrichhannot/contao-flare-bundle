<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Template;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\Event;

class ReaderBuiltEvent extends Event
{
    public function __construct(
        private readonly ContentContext $contentContext,
        private readonly ContentModel $contentModel,
        private readonly ListModel $listModel,
        private Template $template,
        private array $data = [],
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

    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function setTemplate(Template $template): void
    {
        $this->template = $template;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }
}