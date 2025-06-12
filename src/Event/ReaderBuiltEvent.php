<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Model;
use Contao\Template;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\Event;

class ReaderBuiltEvent extends Event
{
    public function __construct(
        private readonly ContentContext    $contentContext,
        private readonly ContentModel      $contentModel,
        private readonly Model             $displayModel,
        private readonly ListModel         $listModel,
        private ReaderPageMetaDto          $pageMeta,
        private Template                   $template,
        private array                      $data = [],
    ) {}

    public function getContentContext(): ContentContext
    {
        return $this->contentContext;
    }

    public function getContentModel(): ContentModel
    {
        return $this->contentModel;
    }

    public function getDisplayModel(): Model
    {
        return $this->displayModel;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getPageMeta(): ReaderPageMetaDto
    {
        return $this->pageMeta;
    }

    public function setPageMeta(ReaderPageMetaDto $pageMeta): self
    {
        $this->pageMeta = $pageMeta;

        return $this;
    }

    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function setTemplate(Template $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}