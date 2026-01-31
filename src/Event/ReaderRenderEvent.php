<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Model;
use Contao\Template;
use HeimrichHannot\FlareBundle\Dto\ReaderPageMetaDto;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class ReaderRenderEvent extends AbstractTemplateRenderEvent
{
    public function __construct(
        private readonly ContentModel      $contentModel,
        private readonly ContextInterface  $context,
        private readonly Model             $displayModel,
        private readonly ListSpecification $listSpecification,
        private ReaderPageMetaDto          $pageMeta,
        private Template                   $template,
    ) {}

    public function getContentModel(): ContentModel
    {
        return $this->contentModel;
    }

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    public function getDisplayModel(): Model
    {
        return $this->displayModel;
    }

    public function getListSpecification(): ListSpecification
    {
        return $this->listSpecification;
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
}