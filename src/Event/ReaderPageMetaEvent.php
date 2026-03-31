<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Model;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

class ReaderPageMetaEvent
{
    private ReaderPageMeta $pageMeta;

    public function __construct(
        private readonly ContentModel      $contentModel,
        private readonly Model             $displayModel,
        private readonly ListSpecification $listSpecification,
        ?ReaderPageMeta                    $pageMeta = null,
    ) {
        $this->pageMeta = $pageMeta ?? new ReaderPageMeta();
    }

    public function getContentModel(): ContentModel
    {
        return $this->contentModel;
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

    public function setPageMeta(ReaderPageMeta $pageMeta): void
    {
        $this->pageMeta = $pageMeta;
    }
}