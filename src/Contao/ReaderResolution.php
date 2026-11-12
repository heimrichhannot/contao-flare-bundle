<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Contao\Reader\ReaderResolver;
use HeimrichHannot\FlareBundle\Engine\Engine;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;

/**
 * What a reader element shows for the request's auto-item, as {@see ReaderResolver} resolved it: shared by the
 * breadcrumb and the reader. Without an entry, either nothing matched (404) or $failure says why membership could not
 * be decided (5xx); the parts built until then are set.
 */
final class ReaderResolution
{
    private ?ReaderPageMeta $resolvedPageMeta = null;

    /**
     * @param ?\Closure(): ReaderPageMeta $pageMeta dispatches the {@see ReaderPageMetaEvent}; set with an entry only
     */
    public function __construct(
        public readonly ContentModel      $contentModel,
        public readonly ?ListCompound     $list = null,
        public readonly ?ValidationIntent $intent = null,
        public readonly ?Engine           $engine = null,
        public readonly ?ValidationView   $view = null,
        public readonly ?Entry            $entry = null,
        public readonly ?FlareException   $failure = null,
        private ?\Closure                 $pageMeta = null,
    ) {}

    /**
     * The entry's page meta: dispatches the {@see ReaderPageMetaEvent} on the first call only; null without an entry.
     */
    public function getPageMeta(): ?ReaderPageMeta
    {
        if ($this->pageMeta !== null)
        {
            $this->resolvedPageMeta = ($this->pageMeta)();
            $this->pageMeta = null;
        }

        return $this->resolvedPageMeta;
    }
}
