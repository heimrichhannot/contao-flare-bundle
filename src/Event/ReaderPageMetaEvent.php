<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;

/**
 * Collects the page meta of a reader's entry. Dispatched once per reader element and auto-item, before the
 * breadcrumb or the reader renders, so that the breadcrumb title equals the `<title>`.
 */
class ReaderPageMetaEvent
{
    public ReaderPageMeta $pageMeta;

    public function __construct(
        public readonly ContentModel $contentModel,
        public readonly Entry        $entry,
        public readonly ListCompound $list,
        ?ReaderPageMeta              $pageMeta = null,
    ) {
        $this->pageMeta = $pageMeta ?? new ReaderPageMeta();
    }
}
