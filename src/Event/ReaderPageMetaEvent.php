<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use Contao\ContentModel;
use Contao\Model;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;

class ReaderPageMetaEvent
{
    public ReaderPageMeta $pageMeta;

    public function __construct(
        public readonly ContentModel $contentModel,
        public readonly Model        $displayModel,
        public readonly ListSpec     $list,
        ?ReaderPageMeta              $pageMeta = null,
    ) {
        $this->pageMeta = $pageMeta ?? new ReaderPageMeta();
    }
}
