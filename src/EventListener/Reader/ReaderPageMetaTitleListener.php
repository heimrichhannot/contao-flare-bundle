<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\EventListener\Reader;

use Contao\CoreBundle\String\HtmlDecoder;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The fallback title, unless a listener set one: the first of the row's headline, title, question, name, alias, id.
 */
#[AsEventListener(priority: -200)]
readonly class ReaderPageMetaTitleListener
{
    public function __construct(
        private HtmlDecoder $htmlDecoder,
    ) {}

    public function __invoke(ReaderPageMetaEvent $event): void
    {
        if ($event->pageMeta->getTitle()) {
            return;
        }

        $row = $event->entry->row;

        $title = $this->htmlDecoder->inputEncodedToPlainText(
            (string) (
                (Str::formatHeadline($row['headline'] ?? null) ?: null)
                ?? $row['title']
                ?? $row['question']
                ?? $row['name']
                ?? $row['alias']
                ?? $row['id']
                ?? ''
            ),
        );

        if (!$title) {
            return;
        }

        $event->pageMeta->setTitle($title);
    }
}
