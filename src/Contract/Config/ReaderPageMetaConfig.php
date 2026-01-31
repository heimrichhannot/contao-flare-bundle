<?php

namespace HeimrichHannot\FlareBundle\Contract\Config;

use Contao\ContentModel;
use Contao\Model;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

readonly class ReaderPageMetaConfig
{
    public function __construct(
        private ContentModel      $contentModel,
        private ContextInterface  $context,
        private Model             $displayModel,
        private ListSpecification $listSpecification,
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
}