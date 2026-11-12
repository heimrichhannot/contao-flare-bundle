<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\ContaoCalendar\EventListener;

use Contao\CalendarEventsModel;
use Contao\Events;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryModelFactory;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
class EventsSchemaOrgListener
{
    public function __construct(
        private readonly EntryModelFactory $entryModelFactory,
    ) {}

    public function __invoke(ReaderSchemaOrgEvent $event): void
    {
        if ($event->entry->meta->modelRef?->table !== CalendarEventsModel::getTable()) {
            return;
        }

        $model = $this->entryModelFactory->create($event->entry);

        if (!$model instanceof CalendarEventsModel) {
            return;
        }

        $event->data = Events::getSchemaOrgData($model);
    }
}
