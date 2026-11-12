<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\EventListener;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\String\HtmlDecoder;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
readonly class EventsReaderPageMetaListener
{
    public function __construct(
        private HtmlDecoder $htmlDecoder,
    ) {}

    public function __invoke(ReaderPageMetaEvent $event): void
    {
        global $objPage;

        if ($event->entry->meta->modelRef?->table !== CalendarEventsModel::getTable()) {
            return;
        }

        $row = $event->entry->row;
        $pageMeta = $event->pageMeta;

        $pageMeta->setTitle($this->htmlDecoder->inputEncodedToPlainText(
            Str::coalesce($row['pageTitle'] ?? null, $row['title'] ?? null, $objPage?->title) ?? ''
        ));

        $description = null;

        if ($row['description'] ?? null) {
            $description = $this->htmlDecoder->inputEncodedToPlainText($row['description']) ?: null;
        }

        if (!$description && ($row['teaser'] ?? null)) {
            $description = Str::htmlToMeta($this->htmlDecoder->inputEncodedToPlainText($row['teaser']), 250) ?: null;
        }

        if ($description) {
            $pageMeta->setDescription($description);
        }

        if ($robots = ($row['robots'] ?? null) ?: null) {
            $pageMeta->setRobots($robots);
        }

        $event->pageMeta = $pageMeta;
    }
}
