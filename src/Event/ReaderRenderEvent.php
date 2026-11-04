<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Model;
use Contao\Template;
use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;
use Symfony\Contracts\EventDispatcher\Event;

class ReaderRenderEvent extends Event
{
    use ModifiesTemplateTrait;

    public function __construct(
        public readonly ContentModel    $contentModel,
        public readonly IntentInterface $intent,
        public readonly Model           $displayModel,
        public readonly ListCompound    $list,
        public ReaderPageMeta           $pageMeta,
        private Template                $template,
    ) {}

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
