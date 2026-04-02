<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\EventListener;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\String\HtmlDecoder;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\Reader\ReaderPageMeta;
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

        /** @var CalendarEventsModel $model */
        $model = $event->getDisplayModel();
        if (!$model instanceof CalendarEventsModel) {
            return;
        }

        $pageMeta = $event->getPageMeta() ?? new ReaderPageMeta();

        $pageMeta->setTitle($this->htmlDecoder->inputEncodedToPlainText(
            Str::coalesce($model->pageTitle, $model->title, $objPage?->title) ?? ''
        ));

        $description = null;

        if ($model->description) {
            $description = $this->htmlDecoder->inputEncodedToPlainText($model->description) ?: null;
        }

        if (!$description && $model->teaser) {
            $description = Str::htmlToMeta($this->htmlDecoder->inputEncodedToPlainText($model->teaser), 250) ?: null;
        }

        if ($description) {
            $pageMeta->setDescription($description);
        }

        if ($robots = $model->robots ?: null) {
            $pageMeta->setRobots($robots);
        }

        $event->setPageMeta($pageMeta);
    }
}