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
        public readonly ContentModel $contentModel,
        public readonly Engine       $engine,
        public readonly ListModel    $listModel,
        private Template              $template,
    ) {}

    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function setTemplate(Template $template): void
    {
        $this->template = $template;
    }
}
