<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\Reader;

use Contao\CoreBundle\String\HtmlDecoder;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: -200)]
readonly class ReaderPageMetaTitleListener
{
    public function __construct(
        private HtmlDecoder $htmlDecoder,
    ) {}

    public function __invoke(ReaderPageMetaEvent $event): void
    {
        $pageMeta = $event->getPageMeta();
        if ($pageMeta->getTitle()) {
            return;
        }

        $model = $event->getDisplayModel();

        $title = $this->htmlDecoder->inputEncodedToPlainText(
            (string) (
                (Str::formatHeadline($model->headline) ?: null)
                ?? $model->title
                ?? $model->question
                ?? $model->name
                ?? $model->alias
                ?? $model->id
            ) ?: '',
        );

        if (!$title) {
            return;
        }

        $pageMeta->setTitle($title);
    }
}