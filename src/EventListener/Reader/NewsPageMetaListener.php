<?php

namespace HeimrichHannot\FlareBundle\EventListener\Reader;

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 190)]
readonly class NewsPageMetaListener
{
    public function __construct(
        private HtmlDecoder $htmlDecoder,
    ) {}

    public function __invoke(ReaderPageMetaEvent $event): void
    {
        global $objPage;

        $model = $event->getDisplayModel();
        if (!$model instanceof NewsModel) {
            return;
        }

        $contentModel = $event->getContentModel();

        $pageMeta = $event->getPageMeta() ?? new ReaderPageMeta();

        $headline = Str::formatHeadline($model->headline) ?: Str::formatHeadline($contentModel->headline);
        $title = $headline ?: $this->htmlDecoder->inputEncodedToPlainText($objPage->title);
        $pageMeta->setTitle($title);

        $teaser = $model->teaser
            ? $this->htmlDecoder->inputEncodedToPlainText($model->teaser)
            : null;

        if ($teaser) {
            $pageMeta->setDescription(Str::htmlToMeta($teaser, 250));
        }

        $event->setPageMeta($pageMeta);
    }
}