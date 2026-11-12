<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Template;
use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\ListCompound;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before the reader renders its entry; listeners may change the template and its data. The page meta is
 * changed through {@see ReaderPageMetaEvent} only.
 */
class ReaderRenderEvent extends Event
{
    use ModifiesTemplateTrait;

    public function __construct(
        public readonly ContentModel    $contentModel,
        public readonly IntentInterface $intent,
        public readonly Entry           $entry,
        public readonly ListCompound    $list,
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
