<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Model;
use Contao\Template;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Contracts\EventDispatcher\Event;

class ReaderRenderEvent extends Event
{
    use ModifiesTemplateTrait;

    public function __construct(
        private readonly ContentModel      $contentModel,
        private readonly ContextInterface  $context,
        private readonly Model             $displayModel,
        private readonly ListSpecification $listSpecification,
        private ReaderPageMeta             $pageMeta,
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

    public function getPageMeta(): ReaderPageMeta
    {
        return $this->pageMeta;
    }

    public function setPageMeta(ReaderPageMeta $pageMeta): self
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